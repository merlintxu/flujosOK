<?php
/**
 * Panel de Administración - Flujos Dimension v4.2.1
 * Funciones: crear token, sync Ringover, batch OpenAI, push Pipedrive
 */

define('ADMIN_ACCESS', true);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
use FlujosDimension\Core\Config;
use FlujosDimension\Core\Database;
use FlujosDimension\Core\JWT;
/* ---------- Carga .env ---------- */
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

/* ---------- Config DB ---------- */
$dbConfig = [
    'host'     => $_ENV['DB_HOST']     ?? 'localhost',
    'port'     => $_ENV['DB_PORT']     ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'flujo_dimen_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'flujodime_user',
    'password' => $_ENV['DB_PASSWORD'] ?? 'RCuaM1/4%6/5'
];

/* ---------- Helpers ---------- */
function db() {
    static $pdo = null;
    global $dbConfig;
    if ($pdo) return $pdo;
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

function statsToday(): array {
    $db = db(); $today = date('Y-m-d');
    $tot   = $db->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at)='$today'")->fetchColumn();
    $ans   = $db->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at)='$today' AND status='answered'")->fetchColumn();
    $dur   = $db->query("SELECT AVG(duration) FROM calls WHERE DATE(created_at)='$today' AND status='answered'")->fetchColumn();
    $pos   = $db->query("SELECT COUNT(*) FROM calls WHERE DATE(created_at)='$today' AND ai_sentiment='positive'")->fetchColumn();
    return [
        'total_calls'    => (int)$tot,
        'answered_calls' => (int)$ans,
        'answer_rate'    => $tot ? round($ans / $tot * 100, 2) : 0,
        'avg_duration'   => (int)$dur,
        'positive_calls' => (int)$pos,
        'positive_rate'  => $tot ? round($pos / $tot * 100, 2) : 0
    ];
}

function recentCalls(int $n = 10): array {
    $stmt = db()->prepare("SELECT phone_number,direction,status,duration,ai_sentiment,created_at FROM calls ORDER BY created_at DESC LIMIT :n");
    $stmt->bindValue(':n', $n, PDO::PARAM_INT); $stmt->execute();
    return $stmt->fetchAll();
}

function apiHealth(): array {
    $oKey = $_ENV['OPENAI_API_KEY']     ?? '';
    $rTok = $_ENV['RINGOVER_API_TOKEN'] ?? '';
    $pTok = $_ENV['PIPEDRIVE_API_TOKEN']?? '';

    $openai    = $oKey && getHttpCode('https://api.openai.com/v1/models', ['Authorization: Bearer '.$oKey]) === 200;
    $ringover  = $rTok && getHttpCode('https://public-api.ringover.com/v2/calls', ['Authorization: '.$rTok]) === 200;
    $pipedrive = $pTok && getHttpCode('https://api.pipedrive.com/v1/users?api_token='.$pTok) === 200;

    return ['database'=>true,'ringover'=>$ringover,'openai'=>$openai,'pipedrive'=>$pipedrive];
}
function getHttpCode(string $url, array $headers=[]): int {
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_HEADER=>0,CURLOPT_HTTPHEADER=>$headers]);
    curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return $code;
}

$stats       = statsToday();
$calls       = recentCalls();
$apisStatus  = apiHealth();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · Flujos Dimension</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            color: white;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 150px;
            justify-content: center;
        }
        
        .tab-btn.active {
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .tab-btn:hover {
            background: white;
            transform: translateY(-1px);
        }
        
        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .calls-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .calls-table th,
        .calls-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .calls-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-answered { background: #d4edda; color: #155724; }
        .status-missed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .sentiment-positive { color: #28a745; }
        .sentiment-negative { color: #dc3545; }
        .sentiment-neutral { color: #6c757d; }
        
        .api-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .api-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .api-card.online {
            background: #d4edda;
            color: #155724;
        }
        
        .api-card.offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            margin: 0.25rem;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 300px;
            font-family: monospace;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .token-display {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #ddd;
            word-break: break-all;
            font-family: monospace;
            margin: 1rem 0;
        }
        
        .tokens-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                min-width: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
    </style>
</head>
<body>
<div class="header"><h1>🚀 Flujos Dimension v4.2.1</h1></div>
<div class="container">
    <div class="tabs">
        <button class="tab-btn active" data-tab="dashboard">📞 Dashboard</button>
        <button class="tab-btn" data-tab="health">💚 Salud</button>
        <button class="tab-btn" data-tab="api">🔑 Gestión API</button>
        <button class="tab-btn" data-tab="tests">🧪 Tests</button>
    </div>

    <!-- DASHBOARD -->
    <div id="dashboard" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card"><h3 id="tot"><?=$stats['total_calls']?></h3><p>Total hoy</p></div>
            <div class="stat-card"><h3 id="ans"><?=$stats['answered_calls']?></h3><p>Respondidas</p></div>
            <div class="stat-card"><h3 id="dur"><?=gmdate('i:s',$stats['avg_duration'])?></h3><p>Duración media</p></div>
            <div class="stat-card"><h3 id="pos"><?=$stats['positive_rate']?>%</h3><p>Positivo AI</p></div>
        </div>

        <h3>Últimas llamadas</h3>
        <table class="calls-table"><thead><tr><th>Fecha</th><th>Teléfono</th><th>Dir.</th><th>Estado</th><th>Dur.</th><th>AI</th></tr></thead>
        <tbody id="tbody-recent"><?php foreach($calls as $c):?>
        <tr>
            <td><?=date('d/m H:i',strtotime($c['created_at']))?></td>
            <td><?=$c['phone_number']?></td>
            <td><?=ucfirst($c['direction'])?></td>
            <td><?=$c['status']?></td>
            <td><?=gmdate('i:s',$c['duration'])?></td>
            <td><?=$c['ai_sentiment']??'-'?></td>
        </tr><?php endforeach;?></tbody></table>

        <!-- SINCRONIZACIONES -->
        <button class="btn" id="btn-sync-ringover">📥 Sync Ringover</button>
        <button class="btn" id="btn-batch-openai">🤖 Batch OpenAI</button>
        <button class="btn" id="btn-push-crm">🏢 Push Pipedrive</button>
        <span id="op-status"></span>
    </div>

    <!-- HEALTH -->
    <div id="health" class="tab-content">
        <div class="api-status">
            <?php foreach($apisStatus as $api=>$ok):?>
            <div class="api-card <?=$ok?'online':'offline'?>">
                <h4><?=strtoupper($api)?></h4><p><?=$ok?'Online':'Offline'?></p>
            </div><?php endforeach;?>
        </div>
    </div>

    <!-- API -->
    <div id="api" class="tab-content">
        <h3>Generar token</h3>
        <input id="tok-name" placeholder="Nombre" value="Token API">
        <select id="tok-dur">
            <option value="indefinite">Indefinido</option><option value="1hour">1h</option>
            <option value="1day">1d</option><option value="1week">1s</option>
        </select>
        <button class="btn" id="btn-make-token">Crear</button>
        <div id="tok-res"></div>
    </div>

    <!-- TESTS -->
    <div id="tests" class="tab-content">
        <button class="btn" id="btn-test-openai">Test OpenAI</button>
        <button class="btn" id="btn-test-ringover">Test Ringover</button>
        <button class="btn" id="btn-test-pipedrive">Test Pipedrive</button>
        <div id="test-res"></div>
    </div>
</div>

<script>
/* --------- Navegación tabs --------- */
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.onclick=()=>{document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
  btn.classList.add('active');document.getElementById(btn.dataset.tab).classList.add('active');}
});

/* --------- Helper ajax --------- */
async function post(url,data){const r=await fetch(url,{method:'POST',body:data});return r.json();}

/* --------- Generar token --------- */
document.getElementById('btn-make-token').onclick=async()=>{
  const fd=new FormData();
  fd.append('token_name',document.getElementById('tok-name').value);
  fd.append('duration',document.getElementById('tok-dur').value);
  const res=await post('api/generate_token.php',fd);
  document.getElementById('tok-res').textContent=res.success?res.token.token:res.message;
};

/* --------- Sync Ringover --------- */
document.getElementById('btn-sync-ringover').onclick=async()=>{
  setStatus('Sincronizando Ringover…');
  const fd=new FormData();fd.append('download',1);
  const r=await post('api/sync_ringover.php',fd);
  setStatus('Insertadas '+r.inserted+' llamadas');
};

/* --------- Batch OpenAI --------- */
document.getElementById('btn-batch-openai').onclick=async()=>{
  setStatus('Procesando IA…');
  const r=await post('api/batch_openai.php',new FormData());
  setStatus('Analizadas '+r.processed+' llamadas');
};

/* --------- Push Pipedrive --------- */
document.getElementById('btn-push-crm').onclick=async()=>{
  setStatus('Subiendo a CRM…');
  const r=await post('api/push_pipedrive.php',new FormData());
  setStatus('Deals creados '+r.deals);
};

/* --------- Tests --------- */
document.getElementById('btn-test-openai').onclick=()=>doTest('openai');
document.getElementById('btn-test-ringover').onclick=()=>doTest('ringover');
document.getElementById('btn-test-pipedrive').onclick=()=>doTest('pipedrive');
async function doTest(which){
  const fd=new FormData();fd.append('action','check_api_status');
  const res=await post('',fd);
  document.getElementById('test-res').textContent=which.toUpperCase()+': '+(res.apis[which]?'OK':'FAIL');
}

/* --------- Utils --------- */
function setStatus(txt){document.getElementById('op-status').textContent=txt;}
</script>
</body></html>

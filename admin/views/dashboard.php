<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Flujos Dimension v4.1</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8em;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .tab {
            flex: 1;
            padding: 15px 20px;
            text-align: center;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            color: #666;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
        }
        
        .content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .stat-card h3 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .change {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #28a745;
        }
        
        .status-card.offline {
            border-left-color: #dc3545;
        }
        
        .status-card.warning {
            border-left-color: #ffc107;
        }
        
        .status-card h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .status-card .status {
            font-size: 16px;
            font-weight: 600;
            color: #28a745;
        }
        
        .status-card.offline .status {
            color: #dc3545;
        }
        
        .status-card.warning .status {
            color: #ffc107;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .api-test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .api-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .api-section h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .token-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .token-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .token-item {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .endpoint-list {
            list-style: none;
            padding: 0;
        }
        
        .endpoint-list li {
            background: #f8f9fa;
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calls-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .calls-table th,
        .calls-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .calls-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-answered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-missed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .test-success {
            background: #d4edda;
            color: #155724;
        }
        
        .test-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .api-test-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🚀 Flujos Dimension v4.1 - Panel de Administración</h1>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'admin'); ?></span>
                <a href="?action=logout" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="tabs">
            <button class="tab active" onclick="showTab('dashboard')">📞 Dashboard Llamadas</button>
            <button class="tab" onclick="showTab('health')">💚 Salud del Sistema</button>
            <button class="tab" onclick="showTab('api')">🔑 Gestión API</button>
            <button class="tab" onclick="showTab('test')">🧪 Test APIs</button>
            <button class="tab" onclick="window.location.href='?action=env_editor'">⚙️ Variables Entorno</button>
        </div>

        <!-- Dashboard de Llamadas -->
        <div id="dashboard" class="content">
            <h2>📞 Dashboard de Llamadas</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>TOTAL LLAMADAS HOY</h3>
                    <div class="number"><?php echo $callStats['total_calls']; ?></div>
                    <div class="change">Datos reales de BD</div>
                </div>
                
                <div class="stat-card">
                    <h3>LLAMADAS RESPONDIDAS</h3>
                    <div class="number"><?php echo $callStats['answered_calls']; ?></div>
                    <div class="change"><?php echo $callStats['answer_rate']; ?>% tasa respuesta</div>
                </div>
                
                <div class="stat-card">
                    <h3>DURACIÓN PROMEDIO</h3>
                    <div class="number"><?php echo $callStats['avg_duration']; ?></div>
                    <div class="change">Calculado desde BD</div>
                </div>
                
                <div class="stat-card">
                    <h3>SENTIMENT POSITIVO</h3>
                    <div class="number"><?php echo $callStats['positive_sentiment']; ?>%</div>
                    <div class="change">Análisis AI real</div>
                </div>
            </div>
            
            <div class="section-title">Últimas Llamadas</div>
            <?php if (!empty($recentCalls)): ?>
                <table class="calls-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                            <th>Duración</th>
                            <th>Sentiment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCalls as $call): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($call['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($call['phone_number'] ?? 'N/A'); ?></td>
                                <td><?php echo ucfirst($call['direction'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $call['status'] ?? 'unknown'; ?>">
                                        <?php echo ucfirst($call['status'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo gmdate('i:s', $call['duration'] ?? 0); ?></td>
                                <td><?php echo ucfirst($call['ai_sentiment'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay llamadas registradas aún. Los datos se mostrarán aquí cuando se sincronicen las llamadas desde Ringover.</p>
            <?php endif; ?>
        </div>

        <!-- Salud del Sistema -->
        <div id="health" class="content" style="display: none;">
            <h2>💚 Salud del Sistema</h2>
            
            <div class="status-grid">
                <div class="status-card <?php echo $this->db->isConnected() ? '' : 'offline'; ?>">
                    <h4>ESTADO BASE DE DATOS</h4>
                    <div class="status"><?php echo $this->db->isConnected() ? 'Online' : 'Offline'; ?></div>
                </div>
                
                <div class="status-card <?php echo $apiStatus['ringover']['success'] ? '' : 'offline'; ?>">
                    <h4>API RINGOVER</h4>
                    <div class="status"><?php echo $apiStatus['ringover']['status'] ?? 'Unknown'; ?></div>
                </div>
                
                <div class="status-card <?php echo $apiStatus['openai']['success'] ? '' : 'offline'; ?>">
                    <h4>API OPENAI</h4>
                    <div class="status"><?php echo $apiStatus['openai']['status'] ?? 'Unknown'; ?></div>
                </div>
                
                <div class="status-card <?php echo $apiStatus['pipedrive']['success'] ? '' : 'offline'; ?>">
                    <h4>API PIPEDRIVE</h4>
                    <div class="status"><?php echo $apiStatus['pipedrive']['status'] ?? 'Unknown'; ?></div>
                </div>
            </div>
            
            <div class="section-title">Métricas de Rendimiento</div>
            <p><strong>Tiempo de respuesta promedio:</strong> Calculado en tiempo real</p>
            <p><strong>Uso de memoria:</strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?>MB</p>
            <p><strong>Uptime del sistema:</strong> <?php echo isset($_SESSION['login_time']) ? gmdate('H:i:s', time() - $_SESSION['login_time']) : 'N/A'; ?></p>
            <p><strong>Última actualización:</strong> <?php echo $apiStatus['timestamp']; ?></p>
        </div>

        <!-- Gestión de API -->
        <div id="api" class="content" style="display: none;">
            <h2>🔑 Gestión de API</h2>
            
            <div class="token-section">
                <h3>Tokens de API Activos</h3>
                <button class="btn" onclick="generateNewToken()">Generar Nuevo Token</button>
                
                <div class="token-list">
                    <?php if (!empty($activeTokens)): ?>
                        <?php foreach ($activeTokens as $token): ?>
                            <div class="token-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($token['name'] ?? 'Token'); ?></strong><br>
                                    <small>Expira: <?php echo date('d/m/Y H:i', strtotime($token['expires_at'])); ?></small>
                                </div>
                                <button class="btn btn-danger" onclick="revokeToken(<?php echo $token['id']; ?>)">Revocar</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No hay tokens activos. Genera uno nuevo para acceder a la API.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="section-title">Endpoints Disponibles</div>
            <ul class="endpoint-list">
                <li>
                    <span><strong>POST</strong> /api/v1/sync/hourly - Sincronización horaria</span>
                    <button class="btn" onclick="testEndpoint('/api/v1/sync/hourly', 'POST')">Probar</button>
                </li>
                <li>
                    <span><strong>GET</strong> /api/v1/sync/status - Estado del sistema</span>
                    <button class="btn" onclick="testEndpoint('/api/v1/sync/status', 'GET')">Probar</button>
                </li>
                <li>
                    <span><strong>GET</strong> /api/v1/calls - Listar llamadas</span>
                    <button class="btn" onclick="testEndpoint('/api/v1/calls', 'GET')">Probar</button>
                </li>
            </ul>
        </div>

        <!-- Test de APIs -->
        <div id="test" class="content" style="display: none;">
            <h2>🧪 Test de APIs Externas</h2>
            
            <div class="api-test-grid">
                <div class="api-section">
                    <h4>🤖 OpenAI API</h4>
                    <button class="btn" onclick="testApi('openai', 'status')">Test Conexión</button>
                    <button class="btn" onclick="testApi('openai', 'completion')">Test Completion</button>
                    <div id="openai-result" class="test-result" style="display: none;"></div>
                </div>
                
                <div class="api-section">
                    <h4>📞 Ringover API</h4>
                    <button class="btn" onclick="testApi('ringover', 'status')">Test Conexión</button>
                    <button class="btn" onclick="testApi('ringover', 'calls')">Obtener Llamadas</button>
                    <div id="ringover-result" class="test-result" style="display: none;"></div>
                </div>
                
                <div class="api-section">
                    <h4>🏢 Pipedrive API</h4>
                    <button class="btn" onclick="testApi('pipedrive', 'status')">Test Conexión</button>
                    <button class="btn" onclick="testApi('pipedrive', 'search')">Buscar Contacto</button>
                    <div id="pipedrive-result" class="test-result" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Ocultar todos los contenidos
            const contents = document.querySelectorAll('.content');
            contents.forEach(content => content.style.display = 'none');
            
            // Remover clase active de todas las pestañas
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Mostrar contenido seleccionado
            document.getElementById(tabName).style.display = 'block';
            
            // Activar pestaña seleccionada
            event.target.classList.add('active');
        }
        
        function testApi(api, test) {
            const resultDiv = document.getElementById(api + '-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<p>Probando ' + api.toUpperCase() + ' API...</p>';
            resultDiv.className = 'test-result';
            
            fetch('?action=api_test&api=' + api + '&test=' + test)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.className = 'test-result test-success';
                        resultDiv.innerHTML = '<strong>✅ Test exitoso</strong><br>' + 
                                            '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
                    } else {
                        resultDiv.className = 'test-result test-error';
                        resultDiv.innerHTML = '<strong>❌ Test fallido</strong><br>' + 
                                            (data.error || 'Error desconocido');
                    }
                })
                .catch(error => {
                    resultDiv.className = 'test-result test-error';
                    resultDiv.innerHTML = '<strong>❌ Error de conexión</strong><br>' + error.message;
                });
        }
        
        function generateNewToken() {
            fetch('?action=generate_token', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Token generado exitosamente:\n\n' + data.token + '\n\nExpira en ' + data.expires_in_hours + ' horas.\n\n¡Guarda este token de forma segura!');
                        location.reload();
                    } else {
                        alert('Error generando token: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
        }
        
        function revokeToken(tokenId) {
            if (confirm('¿Estás seguro de que quieres revocar este token?')) {
                const formData = new FormData();
                formData.append('token_id', tokenId);
                
                fetch('?action=revoke_token', { 
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Token revocado exitosamente');
                        location.reload();
                    } else {
                        alert('Error revocando token: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        function testEndpoint(endpoint, method) {
            alert('Funcionalidad de test de endpoint en desarrollo.\n\nEndpoint: ' + method + ' ' + endpoint);
        }
    </script>
</body>
</html>


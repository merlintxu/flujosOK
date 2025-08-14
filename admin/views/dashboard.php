<?php
// --------- Salvaguardas para evitar "Undefined variable" en producción ----------
if (!isset($callStats) || !is_array($callStats)) {
    $callStats = [
        'total'  => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'errors' => 0,
        'ringover'  => ['today' => 0, 'month' => 0, 'errors' => 0],
        'openai'    => ['requests_today' => 0, 'cost_today' => 0.0],
        'pipedrive' => ['api_calls_today' => 0],
    ];
}
$showOpenAI    = $showOpenAI    ?? true;
$showPipedrive = $showPipedrive ?? true;
$showJwtBtn    = $showJwtBtn    ?? true;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel — Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui, Segoe UI, Roboto, Arial; color:#111827; margin:24px; background:#f8fafc}
  h1{margin:0 0 16px}
  .tabs{display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px}
  .tab, .btn{display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; text-decoration:none; color:#111827}
  .tab.active{background:#2563eb; border-color:#1d4ed8; color:#fff}
  .btn{background:#f9fafb}
  .grid{display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:12px}
  .card{background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px}
  .muted{color:#6b7280}
  .tab-pane{display:none}
  .tab-pane.active{display:block}
  code{background:#f1f5f9; padding:2px 6px; border-radius:6px}
</style>
</head>
<body>

<h1>Dashboard</h1>

<nav class="tabs" id="dashboard-tabs">
  <a class="tab" href="#system-health" data-tab="system-health">🩺 Salud del Sistema</a>
  <a class="tab" href="#api-management" data-tab="api-management">🔑 Gestión API</a>
  <a class="tab" href="#api-test" data-tab="api-test">🧪 Test APIs</a>
  <a class="tab" href="#env" data-tab="env">⚙️ Variables Entorno</a>
  <?php if ($showJwtBtn): ?>
    <!-- Enlace externo a la ruta del Gestor JWT -->
    <a class="tab" href="/admin/?action=jwt" data-external="1">🔐 Gestor JWT</a>
  <?php endif; ?>

  <?php if ($showOpenAI): ?>
    <a class="btn" href="#api-test" data-tab="api-test" id="btn-openai">⚡ OpenAI</a>
  <?php endif; ?>
  <?php if ($showPipedrive): ?>
    <a class="btn" href="#api-test" data-tab="api-test" id="btn-pipedrive">🔗 Pipedrive</a>
  <?php endif; ?>
</nav>

<!-- Pestaña: Salud del Sistema -->
<section class="tab-pane" id="system-health">
  <div class="grid">
    <div class="card">
      <h3>Visión general</h3>
      <p>Total llamadas: <strong><?= (int)$callStats['total'] ?></strong></p>
      <p>Hoy: <strong><?= (int)$callStats['today'] ?></strong> · Semana: <strong><?= (int)$callStats['week'] ?></strong> · Mes: <strong><?= (int)$callStats['month'] ?></strong></p>
      <p>Errores: <strong><?= (int)$callStats['errors'] ?></strong></p>
    </div>
    <div class="card">
      <h3>Ringover</h3>
      <p>Hoy: <strong><?= (int)($callStats['ringover']['today'] ?? 0) ?></strong></p>
      <p>Mes: <strong><?= (int)($callStats['ringover']['month'] ?? 0) ?></strong></p>
      <p>Errores: <strong><?= (int)($callStats['ringover']['errors'] ?? 0) ?></strong></p>
    </div>
    <div class="card">
      <h3>OpenAI</h3>
      <p>Requests hoy: <strong><?= (int)($callStats['openai']['requests_today'] ?? 0) ?></strong></p>
      <p>Coste hoy: <strong><?= number_format((float)($callStats['openai']['cost_today'] ?? 0), 4) ?> €</strong></p>
    </div>
    <div class="card">
      <h3>Pipedrive</h3>
      <p>API calls hoy: <strong><?= (int)($callStats['pipedrive']['api_calls_today'] ?? 0) ?></strong></p>
    </div>
  </div>
</section>

<!-- Pestaña: Gestión API -->
<section class="tab-pane" id="api-management">
  <div class="card">
    <h3>Gestión de APIs</h3>
    <p class="muted">Aquí va tu UI de gestión de claves/servicios (OpenAI, Pipedrive, etc.).</p>
  </div>
</section>

<!-- Pestaña: Test APIs -->
<section class="tab-pane" id="api-test">
  <div class="card">
    <h3>Test de Integraciones</h3>
    <p class="muted">Ejecuta pruebas rápidas contra OpenAI, Pipedrive, Ringover…</p>
    <div style="margin-top:8px">
      <?php if ($showOpenAI): ?>
        <a class="btn" href="#api-test" data-tab="api-test">Probar OpenAI</a>
      <?php endif; ?>
      <?php if ($showPipedrive): ?>
        <a class="btn" href="#api-test" data-tab="api-test">Probar Pipedrive</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Pestaña: Variables de Entorno -->
<section class="tab-pane" id="env">
  <div class="card">
    <h3>Variables de Entorno</h3>
    <p>Ir al editor: <a href="/admin/?action=env_editor"><code>/admin/?action=env_editor</code></a></p>
    <p class="muted">Alias soportado por router: <code>?action=env</code> o <code>?action=env_editor</code>.</p>
  </div>
</section>

<script>
// --- Gestión de pestañas con location.hash ---
(function() {
  function activateTab(tabId) {
    var tabs  = document.querySelectorAll('#dashboard-tabs [data-tab]');
    var panes = document.querySelectorAll('.tab-pane');

    tabs.forEach(function(t){
      if (t.dataset.external === '1') return; // no marcar externos como activos
      t.classList.toggle('active', t.dataset.tab === tabId);
    });
    panes.forEach(function(p){
      p.classList.toggle('active', p.id === tabId);
    });
  }

  function currentHashTab() {
    var h = (window.location.hash || '').replace('#','');
    var known = ['system-health','api-management','api-test','env'];
    if (known.indexOf(h) >= 0) return h;
    return 'system-health';
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Activar al cargar, leyendo hash
    activateTab(currentHashTab());

    // Click en tabs internos → set hash + activar
    document.querySelectorAll('#dashboard-tabs a[data-tab]').forEach(function(a){
      if (a.dataset.external === '1') return;
      a.addEventListener('click', function(ev){
        ev.preventDefault();
        var t = this.getAttribute('data-tab');
        if (t) {
          history.replaceState(null, '', '#' + t);
          activateTab(t);
        }
      });
    });
  });

  // Responder a cambios de hash (back/forward)
  window.addEventListener('hashchange', function(){
    activateTab(currentHashTab());
  });
})();
</script>

</body>
</html>

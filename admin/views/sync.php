<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$success = $success ?? null;
$error   = $error   ?? null;
$result  = $result  ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel — Sincronizar</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px;background:#f8fafc;color:#111827}
  h1{margin:0 0 16px}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px}
  label{display:block;margin-bottom:8px}
  input[type=text],input[type=number],input[type=datetime-local]{width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px}
  .btn{margin-top:8px;padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;cursor:pointer}
  .alert{padding:8px 12px;border-radius:8px;margin-bottom:16px}
  .alert.success{background:#ecfdf5;color:#065f46}
  .alert.error{background:#fef2f2;color:#991b1b}
  pre{background:#f1f5f9;padding:12px;border-radius:8px;overflow:auto}
</style>
</head>
<body>
<h1>Sincronizar</h1>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success,ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error,ENT_QUOTES) ?></div><?php endif; ?>
<div class="grid">
  <form method="post" action="?action=sync.run" class="card">
    <h3>Ringover</h3>
    <label>Desde <input type="datetime-local" name="since"></label>
    <label>Campos <input type="text" name="fields"></label>
    <label><input type="checkbox" name="download" value="1"> Descargar audio</label>
    <label><input type="checkbox" name="full" value="1"> Modo completo</label>
    <input type="hidden" name="job" value="ringover">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <button class="btn" type="submit">Ejecutar</button>
  </form>
  <form method="post" action="?action=sync.run" class="card">
    <h3>Batch OpenAI</h3>
    <label>Máx. por lote <input type="number" name="max" min="1" value="50"></label>
    <input type="hidden" name="job" value="openai">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <button class="btn" type="submit">Ejecutar</button>
  </form>
  <form method="post" action="?action=sync.run" class="card">
    <h3>Pipedrive</h3>
    <label>Límite <input type="number" name="limit" min="1"></label>
    <input type="hidden" name="job" value="pipedrive">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <button class="btn" type="submit">Ejecutar</button>
  </form>
</div>
<?php if ($result): ?>
  <h2>Resultado</h2>
  <pre><?= htmlspecialchars($result['output'] ?? '', ENT_QUOTES) ?></pre>
<?php endif; ?>
</body>
</html>

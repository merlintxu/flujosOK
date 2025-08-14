<?php /** @var array $jwks, $vars, $paths, $currentKid, $success, $error */ ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestor de JWS/JWT</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px;color:#1f2937;background:#f5f6fa}
.card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:16px 0;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer}
.btn-primary{background:#2563eb;border-color:#1d4ed8;color:#fff}
.badge{padding:2px 8px;border-radius:999px;background:#e5e7eb;font-size:.8rem}
.badge-ok{background:#dcfce7}
.alert{padding:12px;border-radius:8px;margin:8px 0}
.alert-ok{background:#ecfdf5;border:1px solid #34d399}
.alert-err{background:#fef2f2;border:1px solid #f87171}
table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #eee;padding:8px;text-align:left}
.kid-active{color:#16a34a;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}
</style></head><body>
<h1>🔐 Gestor de JWS/JWT</h1>
<?php if (!empty($success)): ?><div class="alert alert-ok"><?=htmlspecialchars($success,ENT_QUOTES)?></div><?php endif; ?>
<?php if (!empty($error)):   ?><div class="alert alert-err"><?=htmlspecialchars($error,ENT_QUOTES)?></div><?php endif; ?>

<div class="card">
  <h2>Estado</h2>
  <div class="grid">
    <div><strong>Algoritmo</strong><br><span class="badge <?= ($vars['JWT_ALG']??'HS256')==='RS256'?'badge-ok':'' ?>"><?= htmlspecialchars($vars['JWT_ALG']??'HS256') ?></span></div>
    <div><strong>KID actual</strong><br><span class="badge <?= $currentKid?'badge-ok':'' ?>"><?= htmlspecialchars($currentKid ?: '—') ?></span></div>
    <div><strong>JWKS</strong><br><code><?= htmlspecialchars($paths['jwks']) ?></code></div>
  </div>
  <?php if (($vars['JWT_ALG']??'HS256')==='RS256' && (empty($vars['JWT_KID'])||empty($vars['JWT_PRIVATE_KEY_PATH']))): ?>
    <div class="alert alert-err">⚠️ Falta configuración RS256: define <code>JWT_KID</code> y <code>JWT_PRIVATE_KEY_PATH</code> en <code>.env</code>.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Claves en JWKS</h2>
  <table><thead><tr><th>KID</th><th>Alg</th><th>Uso</th><th></th></tr></thead><tbody>
    <?php foreach (($jwks['keys']??[]) as $k): ?>
    <tr>
      <td class="<?= ($k['kid']??'')===$currentKid ? 'kid-active':'' ?>"><?= htmlspecialchars($k['kid']??'') ?></td>
      <td><?= htmlspecialchars($k['alg']??'') ?></td>
      <td><?= htmlspecialchars($k['use']??'') ?></td>
      <td>
        <form method="post" action="/admin/?action=jwt.rotate" style="display:inline">
          <input type="hidden" name="kid" value="<?= htmlspecialchars($k['kid']??'') ?>">
          <button class="btn">Rotar a este KID</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>

<div class="card">
  <h2>Generar nuevo par RSA</h2>
  <form method="post" action="/admin/?action=jwt.generate">
    <button class="btn btn-primary">Generar y activar</button>
  </form>
</div>

<div class="card">
  <h2>Importar PEM existente</h2>
  <form method="post" action="/admin/?action=jwt.import" enctype="multipart/form-data">
    <p><label>private.pem: <input type="file" name="private_pem" required></label></p>
    <p><label>public.pem (opcional): <input type="file" name="public_pem"></label></p>
    <button class="btn btn-primary">Importar y activar</button>
  </form>
</div>

<div class="card">
  <h2>JWKS público</h2>
  <p>Endpoint: <code>/admin/?action=jwt.jwks</code></p>
  <a class="btn" href="/admin/?action=jwt.jwks" target="_blank">Ver JWKS</a>
</div>

</body></html>

<?php
use App\Core\Session;
/** @var array $errors @var string $email */
$flashes = Session::pullFlashes();
?><!doctype html>
<html lang="fr"<?= !empty($theme) ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — Administration</title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin admin-login">
<main id="contenu" class="auth-wrap">
  <div class="card auth-card">
    <svg class="brand-mark admin-login-mark" viewBox="0 0 48 52" aria-hidden="true" focusable="false"><path d="M24 2 45 14v24L24 50 3 38V14z" class="hex"/><path d="M24 13c5 7.5 8.5 12.4 8.5 17.4a8.5 8.5 0 0 1-17 0C15.5 25.4 19 20.5 24 13z" class="drop"/></svg>
    <h1>Administration</h1>
    <?php foreach ($flashes as $type => $messages): foreach ($messages as $m): ?><p class="flash flash-<?= e($type) ?>"><?= e($m) ?></p><?php endforeach; endforeach; ?>
    <?php if ($errors): ?><p class="flash flash-error" role="alert"><?= e(implode(' ', $errors)) ?></p><?php endif; ?>
    <form action="/admin/connexion" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="f-email">E-mail</label><input id="f-email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"></div>
      <div class="field"><label for="f-password">Mot de passe</label><input id="f-password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
    </form>
    <p class="muted small">Accès réservé au personnel. Session fermée après 2 heures d'inactivité.</p>
  </div>
</main>
</body>
</html>

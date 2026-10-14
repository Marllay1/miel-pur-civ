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
<?= favicon_tags() ?><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin admin-login">
<main id="contenu" class="auth-wrap">
  <div class="card auth-card">
    <p class="admin-login-brand"><?= brand_html() ?></p>
    <h1>Administration</h1>
    <?php foreach ($flashes as $type => $messages): foreach ($messages as $m): ?><p class="flash flash-<?= e($type) ?>"><?= e($m) ?></p><?php endforeach; endforeach; ?>
    <?php if ($errors): ?><p class="flash flash-error" role="alert"><?= e(implode(' ', $errors)) ?></p><?php endif; ?>
    <form action="/admin/connexion" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="retour" value="<?= e($retour ?? '/admin') ?>">
      <div class="field"><label for="f-email">E-mail</label><input id="f-email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"></div>
      <div class="field"><label for="f-password">Mot de passe</label><input id="f-password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
    </form>
    <p class="muted small">Accès réservé au personnel. Session fermée après 2 heures d'inactivité.</p>
    <p class="small"><a href="/">← Retour au site</a></p>
  </div>
</main>
</body>
</html>

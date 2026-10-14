<?php use App\Core\Auth; use App\Core\View; /** @var array $staff @var array $roles @var array $perms @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<h1>Équipe et rôles</h1>
<section class="card">
  <h2>Rôles et permissions</h2>
  <dl class="roles-list">
    <?php foreach ($roles as $role): ?><dt><?= e($role['label']) ?> <code><?= e($role['name']) ?></code></dt><dd><?= e(implode(' · ', $perms[(int) $role['id']] ?? ['—'])) ?></dd><?php endforeach; ?>
  </dl>
  <p class="small muted">Les permissions sont vérifiées côté serveur pour chaque page et chaque action.</p>
</section>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Membre</th><th>Dernière connexion</th><th>Rôle et état</th></tr></thead>
  <tbody>
  <?php foreach ($staff as $s): $self = (int) $s['id'] === Auth::id(); ?>
    <tr>
      <td><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong><?= $self ? ' <span class="badge">vous</span>' : '' ?><br><span class="small"><?= e($s['email']) ?></span></td>
      <td class="small"><?= e(date_fr($s['last_login_at'])) ?></td>
      <td>
        <form action="/admin/equipe/<?= (int) $s['id'] ?>" method="post" class="inline-form">
          <?= csrf_field() ?>
          <label class="sr-only" for="r-<?= (int) $s['id'] ?>">Rôle</label>
          <select id="r-<?= (int) $s['id'] ?>" name="role"<?= $self ? ' disabled' : '' ?>><?php foreach ($roles as $role): ?><option value="<?= e($role['name']) ?>"<?= $s['role'] === $role['name'] ? ' selected' : '' ?>><?= e($role['label']) ?></option><?php endforeach; ?></select>
          <label class="check"><input type="checkbox" name="is_active" value="1"<?= (int) $s['is_active'] ? ' checked' : '' ?><?= $self ? ' disabled' : '' ?>> Actif</label>
          <?php if (!$self): ?><button class="btn btn-ghost btn-small" type="submit">Enregistrer</button><?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<form action="/admin/equipe" method="post" class="card form-grid" novalidate>
  <?= csrf_field() ?>
  <h2 class="field-full">Ajouter un membre</h2>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field"><label for="t-first">Prénom</label><input id="t-first" name="first_name" required maxlength="100" value="<?= $val('first_name') ?>"></div>
  <div class="field"><label for="t-last">Nom</label><input id="t-last" name="last_name" required maxlength="100" value="<?= $val('last_name') ?>"></div>
  <div class="field"><label for="t-email">E-mail</label><input id="t-email" name="email" type="email" required maxlength="190" value="<?= $val('email') ?>"></div>
  <div class="field"><label for="t-role">Rôle</label><select id="t-role" name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role['name']) ?>"<?= ($old['role'] ?? 'order_manager') === $role['name'] ? ' selected' : '' ?>><?= e($role['label']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="t-pass">Mot de passe provisoire <span class="hint">(10 caractères, lettres et chiffres)</span></label><input id="t-pass" name="password" type="password" autocomplete="new-password" required minlength="10"></div>
  <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Créer le compte</button></div>
</form>

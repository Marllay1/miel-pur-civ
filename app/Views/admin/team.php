<?php use App\Core\Auth; use App\Core\View; /** @var array $staff @var array $roles @var array $perms @var array $errors @var array $old */ $val = static fn (string $k) => e($old[$k] ?? ''); ?>
<h1>Équipe</h1>
<section class="card">
  <h2>Rôle : Administrateur</h2>
  <p>Chaque membre de l'équipe est <strong>administrateur</strong> et a accès à toute l'administration :</p>
  <?php foreach ($roles as $role): ?><p class="small"><?= e(implode(' · ', $perms[(int) $role['id']] ?? ['—'])) ?></p><?php endforeach; ?>
  <p class="small muted">Désactivez le compte d'une personne qui quitte l'équipe : elle ne peut plus se connecter. L'accès reste contrôlé côté serveur pour chaque page et chaque action ; le dernier administrateur actif ne peut pas être désactivé.</p>
</section>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Membre</th><th>Dernière connexion</th><th>État</th></tr></thead>
  <tbody>
  <?php foreach ($staff as $s): $self = (int) $s['id'] === Auth::id(); ?>
    <tr>
      <td><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong><?= $self ? ' <span class="badge">vous</span>' : '' ?><br><span class="small"><?= e($s['email']) ?></span></td>
      <td class="small"><?= e(date_fr($s['last_login_at'])) ?></td>
      <td>
        <form action="/admin/equipe/<?= (int) $s['id'] ?>" method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="role" value="admin">
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
  <h2 class="field-full">Ajouter un administrateur</h2>
  <div class="field-full"><?= View::partial('errors-summary', ['errors' => $errors]) ?></div>
  <div class="field"><label for="t-first">Prénom</label><input id="t-first" name="first_name" required maxlength="100" value="<?= $val('first_name') ?>"></div>
  <div class="field"><label for="t-last">Nom</label><input id="t-last" name="last_name" required maxlength="100" value="<?= $val('last_name') ?>"></div>
  <div class="field"><label for="t-email">E-mail</label><input id="t-email" name="email" type="email" required maxlength="190" value="<?= $val('email') ?>"></div>
  <input type="hidden" name="role" value="admin">
  <div class="field"><label for="t-pass">Mot de passe provisoire <span class="hint">(10 caractères, lettres et chiffres)</span></label><input id="t-pass" name="password" type="password" autocomplete="new-password" required minlength="10"></div>
  <div class="field-full"><button class="btn btn-primary btn-small" type="submit">Créer le compte</button></div>
</form>

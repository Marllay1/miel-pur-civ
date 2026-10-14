<?php use App\Core\View; /** @var array $messages @var array $replies */ ?>
<div class="wrap page-head"><h1><?= e(t('account.messages')) ?></h1></div>
<div class="wrap account-layout">
  <?= View::partial('account-nav', ['current' => '/compte/messages']) ?>
  <div>
    <p class="btn-row"><a class="btn" href="/contact"><?= icon('chat') ?><?= e(t('account.new_message')) ?></a></p>
    <?php if (!$messages): ?>
    <p class="group cell muted"><?= e(t('account.no_messages')) ?></p>
    <?php endif; ?>
    <?php foreach ($messages as $m): ?>
    <article class="group message-thread">
      <div class="cell"><span class="cell-body"><span class="cell-title"><?= e($m['subject']) ?></span><br>
        <span class="cell-sub"><?= e(date_fr($m['created_at'])) ?> · <?= e(t('contact.reason.' . $m['reason'])) ?> · <?= e(t('message.status.' . $m['status'])) ?></span></span></div>
      <div class="cell"><div class="message-body"><?= nl2br(e($m['message'])) ?></div></div>
      <?php foreach ($replies[(int) $m['id']] ?? [] as $x): ?>
      <div class="cell reply-cell"><div><p class="small muted"><?= e(t('account.reply_from', ['date' => date_fr($x['created_at'])])) ?></p><div class="message-body"><?= nl2br(e($x['body'])) ?></div></div></div>
      <?php endforeach; ?>
    </article>
    <?php endforeach; ?>
  </div>
</div>

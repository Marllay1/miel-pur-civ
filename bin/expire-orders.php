<?php
declare(strict_types=1);

/**
 * Tâche planifiée (toutes les 5 à 15 minutes) :
 *  - demande aux prestataires l'état des paiements en ligne restés « en cours » (notification perdue) ;
 *  - annule les commandes en attente de paiement dont le délai est dépassé, après une dernière vérification
 *    auprès du prestataire (stock et code promo restitués) ;
 *  - purge les compteurs anti-abus expirés et les paniers invités abandonnés depuis plus de 60 jours.
 * Exemple cron : *\/10 * * * * php /chemin/miel-pur-ci/bin/expire-orders.php >> /chemin/miel-pur-ci/storage/logs/cron.log 2>&1
 */

require __DIR__ . '/_cli.php';

use App\Core\DB;
use App\Core\RateLimiter;
use App\Services\OrderService;
use App\Services\PaymentService;

$verified = PaymentService::verifyPending();
$expired = OrderService::expireOverdue();
$limits = RateLimiter::purgeExpired();
$carts = DB::run('DELETE FROM carts WHERE user_id IS NULL AND updated_at < ?', [gmdate('Y-m-d H:i:s', time() - 60 * 86400)])->rowCount();
$resets = DB::run('DELETE FROM password_resets WHERE expires_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)])->rowCount();
out(sprintf('[%s] paiements vérifiés : %d · commandes expirées : %d · compteurs purgés : %d · paniers abandonnés supprimés : %d · liens de réinitialisation purgés : %d',
    gmdate('Y-m-d H:i:s'), $verified, $expired, $limits, $carts, $resets));

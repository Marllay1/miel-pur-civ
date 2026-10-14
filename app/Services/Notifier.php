<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Mailer;

/**
 * E-mails transactionnels, envoyés dans la langue de la commande (ou du client).
 * Le lien personnel de commande (jeton secret) ne figure que dans l'e-mail de confirmation.
 */
final class Notifier
{
    /** Exécute $fn dans la langue demandée puis restaure la langue courante. */
    private static function in(string $locale, callable $fn): void
    {
        $previous = Lang::get();
        Lang::set($locale);
        try {
            $fn();
        } finally {
            Lang::set($previous);
        }
    }

    private static function signature(): string
    {
        return "\n\n— " . Settings::get('store_name') . "\n" . abs_url('/') . (Settings::get('store_phone') !== '' ? "\n" . SiteConfig::internationalPhone(Settings::get('store_phone')) : '');
    }

    public static function orderPlaced(array $order, string $token): void
    {
        self::in($order['locale'] ?? 'fr', static function () use ($order, $token): void {
            $items = OrderService::items((int) $order['id']);
            $lines = array_map(static fn ($i) => sprintf('- %s – %s × %d : %s', $i['product_name'], $i['variant_label'], $i['quantity'], money((int) $i['line_total'])), $items);
            $link = abs_url('/commande/' . $order['number'] . '?t=' . $token);
            $payment = $order['status'] === 'pending_payment'
                ? t('mail.placed.pay_online', ['method' => payment_method_label($order['payment_method'])])
                : t('mail.placed.cod');
            $manual = PaymentMethods::find($order['payment_method']);
            if ($manual !== null && $manual['kind'] === 'manual') {
                // Paiement manuel : instructions saisies en administration ; la commande n'est confirmée qu'après vérification par l'équipe.
                $payment = t('mail.placed.manual', ['method' => payment_method_label($order['payment_method']), 'total' => money(PaymentLedger::dueNow($order)), 'number' => $order['number']])
                    . ((string) $manual['receiver_number'] !== '' ? "\n" . t('order.manual_receiver') . ' : ' . $manual['receiver_number'] : '')
                    . ((string) $manual['beneficiary'] !== '' ? "\n" . t('order.manual_beneficiary') . ' : ' . $manual['beneficiary'] : '')
                    . (PaymentMethods::instructions($manual) !== '' ? "\n" . PaymentMethods::instructions($manual) : '');
            }
            if ($order['payment_terms'] === 'invoice') {
                $payment = t('mail.placed.invoice', ['date' => $order['balance_due_at'] ? date_fr($order['balance_due_at'], false) : '—']);
            }
            if ($order['payment_terms'] === 'deposit') {
                $payment .= "\n" . t('mail.placed.deposit', ['deposit' => money((int) $order['deposit_amount']), 'balance' => money((int) $order['total'] - (int) $order['deposit_amount'])])
                    . (PaymentPolicy::terms('deposit_terms') !== '' ? "\n" . PaymentPolicy::terms('deposit_terms') : '');
            }
            $body = t('mail.hello', ['name' => $order['customer_first_name']]) . "\n\n"
                . t('mail.placed.intro', ['number' => $order['number']]) . "\n\n"
                . implode("\n", $lines)
                . "\n\n" . t('totals.subtotal') . ' : ' . money((int) $order['subtotal'])
                . ((int) $order['discount'] > 0 ? "\n" . t('totals.discount') . ' : -' . money((int) $order['discount']) : '')
                . "\n" . t('totals.shipping') . ' (' . $order['shipping_zone_name'] . ') : ' . money((int) $order['shipping_fee'])
                . "\n" . t('totals.total') . ' : ' . money((int) $order['total'])
                . "\n\n" . $payment . "\n\n" . t('mail.placed.link') . "\n" . $link
                . self::signature();
            Mailer::send($order['customer_email'], t('mail.placed.subject', ['number' => $order['number']]), $body);
        });

        $admin = Settings::get('order_notification_email');
        if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
            self::in('fr', static function () use ($order, $admin): void {
                Mailer::send($admin, 'Nouvelle commande ' . $order['number'] . ' – ' . money((int) $order['total']),
                    "Nouvelle commande {$order['number']} ({$order['customer_first_name']} {$order['customer_last_name']}, {$order['customer_phone']}).\n"
                    . 'Paiement : ' . payment_method_label($order['payment_method'])
                    . ($order['payment_terms'] === 'deposit' ? ' (acompte ' . money((int) $order['deposit_amount']) . ')' : '')
                    . ($order['payment_terms'] === 'invoice' ? ' (paiement sur facture, échéance ' . ($order['balance_due_at'] ? date_fr($order['balance_due_at'], false) : '—') . ')' : '')
                    . ((int) $order['is_wholesale'] === 1 ? "\nCommande en gros." : '')
                    . "\nStatut : " . status_label($order['status']) . "\n" . base_url() . '/admin/commandes/' . $order['id']);
            });
        }
    }

    public static function statusChanged(array $order): void
    {
        if (!Lang::has('mail.status.' . $order['status'], 'fr')) {
            return;
        }
        self::in($order['locale'] ?? 'fr', static function () use ($order): void {
            $body = t('mail.hello', ['name' => $order['customer_first_name']]) . "\n\n"
                . t('mail.status.line', ['number' => $order['number'], 'status' => status_label($order['status'])]) . "\n"
                . t('mail.status.' . $order['status']) . "\n\n" . t('mail.status.track') . ' ' . abs_url('/suivi-commande') . self::signature();
            Mailer::send($order['customer_email'], t('mail.status.subject', ['number' => $order['number'], 'status' => status_label($order['status'])]), $body);
        });
    }

    public static function passwordReset(array $user, string $token, string $locale = 'fr'): void
    {
        self::in($locale, static function () use ($user, $token): void {
            $link = abs_url('/compte/reinitialiser/' . $token);
            Mailer::send($user['email'], t('mail.reset.subject'),
                t('mail.hello', ['name' => $user['first_name']]) . "\n\n" . t('mail.reset.body') . "\n" . $link . "\n\n" . t('mail.reset.ignore') . self::signature());
        });
    }

    public static function staffNotice(string $subject, string $body): void
    {
        $to = Settings::get('order_notification_email');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Mailer::send($to, $subject, $body . self::signature());
        }
    }
}

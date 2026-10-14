<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;

/**
 * E-mails transactionnels (texte brut). Le lien de commande contient le jeton secret uniquement dans l'e-mail initial.
 */
final class Notifier
{
    private static function signature(): string
    {
        return "\n\n— " . Settings::get('store_name') . "\n" . base_url();
    }

    public static function orderPlaced(array $order, string $token): void
    {
        $items = OrderService::items((int) $order['id']);
        $lines = array_map(static fn ($i) => sprintf('- %s – %s × %d : %s', $i['product_name'], $i['variant_label'], $i['quantity'], money((int) $i['line_total'])), $items);
        $link = abs_url('/commande/' . $order['number'] . '?t=' . $token);
        $payment = $order['status'] === 'pending_payment'
            ? "Votre commande sera confirmée après réception du paiement (" . payment_method_label($order['payment_method']) . ")."
            : 'Paiement à la livraison : notre équipe vous contactera pour confirmer la commande et le créneau de livraison.';
        $body = "Bonjour {$order['customer_first_name']},\n\nNous avons bien reçu votre commande {$order['number']}.\n\n"
            . implode("\n", $lines)
            . "\n\nSous-total : " . money((int) $order['subtotal'])
            . ((int) $order['discount'] > 0 ? "\nRemise : -" . money((int) $order['discount']) : '')
            . "\nLivraison ({$order['shipping_zone_name']}) : " . money((int) $order['shipping_fee'])
            . "\nTotal : " . money((int) $order['total'])
            . "\n\n$payment\n\nSuivre votre commande (lien personnel, ne le partagez pas) :\n$link"
            . self::signature();
        Mailer::send($order['customer_email'], 'Commande ' . $order['number'] . ' reçue', $body);

        $admin = Settings::get('order_notification_email');
        if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
            Mailer::send($admin, 'Nouvelle commande ' . $order['number'] . ' – ' . money((int) $order['total']),
                "Nouvelle commande {$order['number']} ({$order['customer_first_name']} {$order['customer_last_name']}, {$order['customer_phone']}).\n"
                . 'Statut : ' . status_label($order['status']) . "\n" . abs_url('/admin/commandes/' . $order['id']));
        }
    }

    public static function statusChanged(array $order): void
    {
        $messages = [
            'paid' => 'Votre paiement a bien été reçu. Nous préparons votre commande.',
            'preparing' => 'Votre commande est confirmée et en cours de préparation.',
            'shipped' => 'Votre commande a été remise au livreur.',
            'delivered' => 'Votre commande est indiquée comme livrée. Merci pour votre confiance !',
            'cancelled' => 'Votre commande a été annulée. Contactez-nous pour toute question.',
            'refunded' => 'Votre commande a été remboursée.',
        ];
        if (!isset($messages[$order['status']])) {
            return;
        }
        $body = "Bonjour {$order['customer_first_name']},\n\nCommande {$order['number']} : " . status_label($order['status']) . ".\n"
            . $messages[$order['status']] . "\n\nSuivi : " . abs_url('/suivi-commande') . self::signature();
        Mailer::send($order['customer_email'], 'Commande ' . $order['number'] . ' : ' . status_label($order['status']), $body);
    }

    public static function passwordReset(array $user, string $token): void
    {
        $link = abs_url('/compte/reinitialiser/' . $token);
        Mailer::send($user['email'], 'Réinitialisation de votre mot de passe',
            "Bonjour {$user['first_name']},\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n$link\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message." . self::signature());
    }

    public static function staffNotice(string $subject, string $body): void
    {
        $to = Settings::get('order_notification_email');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Mailer::send($to, $subject, $body . self::signature());
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Échec de création d'une transaction chez un prestataire (message affichable au client). */
final class PaymentException extends \RuntimeException
{
}

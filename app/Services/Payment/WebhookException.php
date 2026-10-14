<?php
declare(strict_types=1);

namespace App\Services\Payment;

final class WebhookException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message, $status);
    }
}

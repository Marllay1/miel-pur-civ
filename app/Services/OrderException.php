<?php
declare(strict_types=1);

namespace App\Services;

/** Refus métier pendant la création d'une commande (annule la transaction). */
final class OrderException extends \RuntimeException
{
    public function __construct(public readonly array $errors, public readonly ?int $newTotal = null)
    {
        parent::__construct(implode(' ', $errors));
    }
}

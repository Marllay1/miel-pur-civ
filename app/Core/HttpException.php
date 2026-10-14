<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Erreur HTTP. $kind précise le type d'erreur (titre et texte de la page) quand le code seul ne suffit pas :
 * ex. 403 + « csrf » = formulaire expiré. Seuls des codes HTTP standard sont émis (Apache remplace les autres par 500).
 */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly string $kind = '')
    {
        parent::__construct($message, $status);
    }
}

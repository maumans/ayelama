<?php

namespace App\Enums;

/**
 * Comment l'argent a circulé — encaissement d'un client ou versement à un organisme.
 *
 * La liste vivait seulement dans `ModalEnregistrerPaiement.jsx` ; le serveur acceptait n'importe
 * quelle chaîne de 30 caractères (`moyen_paiement` : 9 « especes », 1 chaîne vide en base le
 * 2026-11-23). Elle est désormais **déclarée ici** et servie aux écrans par
 * `HandleInertiaRequests` — aucun miroir JavaScript à tenir d'accord.
 */
enum MoyenPaiement: string
{
    case Especes     = 'especes';
    case Cheque      = 'cheque';
    case Virement    = 'virement';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::Especes     => 'Espèces',
            self::Cheque      => 'Chèque',
            self::Virement    => 'Virement',
            self::MobileMoney => 'Mobile money',
        };
    }

    /** @return list<array{valeur: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['valeur' => $c->value, 'label' => $c->label()], self::cases());
    }
}

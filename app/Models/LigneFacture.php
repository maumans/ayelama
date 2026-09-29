<?php

namespace App\Models;

use App\Enums\TypeRemise;
use Illuminate\Database\Eloquent\Model;

class LigneFacture extends Model
{
    protected $table = 'lignes_factures';

    protected $fillable = [
        'facture_id',
        'bareme_id',
        'designation',
        'quantite',
        'montant',
        'remise_autorisee',
        'remise_type',
        'remise_valeur',
    ];

    protected function casts(): array
    {
        return [
            'quantite'      => 'integer',
            'montant'       => 'decimal:2',
            'remise_autorisee' => 'boolean',
            'remise_type'   => TypeRemise::class,
            'remise_valeur' => 'decimal:2',
        ];
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class);
    }

    /**
     * Barème d'origine — `null` pour une ligne ajoutée à la main.
     *
     * Sert à deux choses : savoir si la ligne est remisable, et **retrouver sa remise après une
     * régénération de facture**, qui supprime et recrée toutes les lignes.
     */
    public function bareme()
    {
        return $this->belongsTo(Bareme::class);
    }

    /** Montant avant remise : quantité × montant unitaire. */
    public function montantBrut(): float
    {
        return round($this->quantite * (float) $this->montant, 2);
    }

    /**
     * Montant de la remise, en francs — calculé, jamais stocké.
     *
     * Une seule source de vérité : le **type** et la **valeur saisie**. Stocker aussi le
     * résultat ferait diverger les deux au premier changement de tarif, et c'est justement ce
     * que la régénération de facture provoque.
     */
    public function remiseMontant(): float
    {
        if (! $this->remise_type || ! $this->remise_valeur) {
            return 0.0;
        }

        return $this->remise_type->montantSurBrut((float) $this->remise_valeur, $this->montantBrut());
    }

    /**
     * La remise exprimée en pourcentage, quelle que soit sa forme de saisie.
     *
     * C'est l'autre face de `remiseMontant()` : le formulaire lie les deux, l'utilisateur en
     * saisit une et voit l'autre.
     */
    public function remisePourcentage(): float
    {
        $brut = $this->montantBrut();

        return $brut > 0 ? round($this->remiseMontant() / $brut * 100, 2) : 0.0;
    }

    /** Montant total de cette ligne, **remise déduite**. */
    public function total(): float
    {
        return round($this->montantBrut() - $this->remiseMontant(), 2);
    }

    /**
     * Cette ligne peut-elle porter une remise ?
     *
     * Le drapeau est **porté par la ligne**, copié du barème à la génération, et non relu à
     * chaque affichage. Une facture émise garde ainsi la règle qui valait au moment où elle a
     * été établie — même doctrine que l'instantané des données attendues d'une formalité.
     *
     * La distinction vient du métier : les honoraires sont la marge de l'étude, un débours est
     * de l'argent qu'elle avance pour le client. Remiser 180 000 GNF de greffe ne réduirait pas
     * une marge, cela ferait perdre 180 000 GNF réels.
     */
    public function remiseAutorisee(): bool
    {
        return (bool) $this->remise_autorisee;
    }

    /**
     * La remise dépasse-t-elle ce que la ligne porte aujourd'hui ?
     *
     * Le cas naît d'un tarif revu à la baisse après coup : une remise de 500 000 GNF sur une
     * ligne tombée à 300 000. `TypeRemise::montantSurBrut()` la plafonne pour que le total ne
     * devienne pas négatif, mais le silence serait pire — l'écart se voit à l'écran.
     */
    public function remiseDepasseLaLigne(): bool
    {
        return $this->remise_type === TypeRemise::Montant
            && (float) $this->remise_valeur > $this->montantBrut();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\TypeActe;
use App\Services\ActesGeneratorService;
use App\Support\VariantesTypeActe;
use Illuminate\Http\Request;

class TypeActeController extends Controller
{
    /**
     * Actes qu'une procédure produira, avant que le dossier existe.
     *
     * Alimente la carte « Actes à produire » du récapitulatif de l'assistant. Elle était jusqu'ici
     * remplie depuis `TypeActe::modeles()`, c'est-à-dire l'ancienne colonne `type_acte_id` : elle
     * annonçait donc autre chose que ce qui serait généré — un gabarit partagé n'y figurait pas, un
     * gabarit détaché y restait, et les variantes étaient purement ignorées.
     *
     * Le calcul se fait ici et non en JavaScript : la résolution rôle × variante × `applicable_tous`
     * est la règle métier, et l'écrire une seconde fois côté client garantirait la divergence entre
     * ce qui est annoncé et ce qui est produit.
     */
    public function actesPrevus(Request $request, TypeActe $typeActe, ActesGeneratorService $generateur)
    {
        // Même porte que la création d'un dossier : cet aperçu ne révèle rien de plus que ce que
        // l'assistant montrera à la ligne suivante.
        $this->authorize('create', Dossier::class);

        $connues = array_column(VariantesTypeActe::options($typeActe->code), 'valeur');

        $data = $request->validate([
            'variantes'   => ['sometimes', 'array'],
            'variantes.*' => ['string', 'in:' . implode(',', $connues ?: ['-'])],
        ]);

        $actes = $generateur->actesPrevus($typeActe, $data['variantes'] ?? []);

        return response()->json([
            'actes' => array_map(fn (array $a) => [
                'nom'           => $a['nom'],
                'type_document' => $a['type_document'],
                'role_label'    => ModeleActe::TYPES_DOCUMENT[$a['type_document']] ?? $a['type_document'],
                // `false` = la procédure attend ce document mais aucun gabarit actif ne le produit.
                // Le signaler ici, c'est permettre de le découvrir avant de créer le dossier plutôt
                // qu'en générant.
                'a_gabarit'     => $a['a_gabarit'],
            ], $actes),
        ]);
    }
}

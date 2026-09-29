<?php

namespace App\Http\Requests;

use App\Enums\DonneeAuRetour;
use App\Models\Formalite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Enregistrement du retour d'une formalité : résultat, date, et les données délivrées.
 *
 * Extrait en `FormRequest` parce que les règles sont **construites depuis la déclaration de la
 * formalité** : un champ par donnée attendue. Les composer dans le contrôleur y aurait mêlé
 * deux métiers, et surtout les aurait rendues intestables sans passer par HTTP —
 * `$request->rules()` s'assert directement.
 *
 * ⚠️ **Le payload imbrique les données sous `donnees_recues`**, plutôt que de les aplatir en
 * `donnee_rccm_numero`. `validated()` **écarte en silence** ce qui n'est pas validé — le défaut
 * déjà documenté pour `StoreDossierRequest::partiesRules()`, qui faisait disparaître des champs
 * de parties sans un mot. Un sous-tableau permet de générer les règles depuis **la même source**
 * que celle qui a produit les clés : les deux ensembles ne peuvent pas diverger.
 */
class EnregistrerRetourFormaliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation fine reste dans le contrôleur (`gererFormalites` sur le dossier) :
        // elle porte sur le dossier, pas sur la requête.
        return true;
    }

    private function formalite(): Formalite
    {
        return $this->route('formalite');
    }

    public function rules(): array
    {
        $regles = [
            'resultat'                => ['required', 'string', 'in:recu,rejete'],
            'date_retour'             => ['required', 'date'],
            // Motif obligatoire **seulement** sur rejet : sans lui, « à corriger et redéposer »
            // ne dit pas quoi corriger, et le formaliste rappelle l'organisme.
            'motif_rejet'             => ['required_if:resultat,rejete', 'nullable', 'string', 'max:500'],
            'reference_document_recu' => ['nullable', 'string', 'max:200'],
            'donnees_recues'          => ['nullable', 'array'],
        ];

        foreach (DonneeAuRetour::depuis($this->formalite()->donnees_au_retour) as $donnee) {
            // ⚠️ **Aucune donnée n'est obligatoire.** L'APIP peut rendre un extrait sans le NIF.
            // L'exiger reproduirait le piège de `modification.soc.rccm required:true` : une
            // étape bloquée par une donnée que l'organisme n'a pas fournie. On saisit ce qui
            // est là, et le manque est signalé — jamais opposé.
            $regles['donnees_recues.' . $donnee->value] = $donnee->regles();
        }

        return $regles;
    }

    public function messages(): array
    {
        return [
            'motif_rejet.required_if' => 'Précisez ce que l\'organisme reproche : c\'est ce que le formaliste devra corriger.',
        ];
    }

    /**
     * Contrôle de cohérence que les règles champ à champ ne peuvent pas porter.
     *
     * Une date d'immatriculation postérieure au retour est impossible : l'autorité ne délivre
     * pas un document daté de demain. Le contrôle vaut pour toute donnée de type date.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $retour = $this->date('date_retour');

            if (! $retour) {
                return;
            }

            foreach (DonneeAuRetour::depuis($this->formalite()->donnees_au_retour) as $donnee) {
                if ($donnee->type() !== 'date') {
                    continue;
                }

                $valeur = $this->input('donnees_recues.' . $donnee->value);

                if (blank($valeur)) {
                    continue;
                }

                if (strtotime((string) $valeur) > $retour->getTimestamp()) {
                    $v->errors()->add(
                        'donnees_recues.' . $donnee->value,
                        sprintf('« %s » ne peut pas être postérieure à la date de retour.', $donnee->label()),
                    );
                }
            }
        });
    }

    /**
     * Les données effectivement saisies, vides écartées.
     *
     * @return array<string, mixed>
     */
    public function donneesSaisies(): array
    {
        return array_filter(
            $this->validated()['donnees_recues'] ?? [],
            fn ($valeur) => filled($valeur),
        );
    }
}

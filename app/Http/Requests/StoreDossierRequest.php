<?php

namespace App\Http\Requests;

use App\Enums\CategorieActe;
use App\Enums\FormeTitreRepresentation;
use App\Enums\MotifRepresentation;
use App\Enums\RoleUtilisateur;
use App\Rules\UtilisateurPossedeRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Dossier::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type_acte_id'  => ['required', 'integer', 'exists:types_actes,id'],
            // Société du registre sur laquelle porte le dossier — renseignée pour une
            // modification ou une dissolution (fiche choisie), créée après coup pour une
            // constitution (voir DossierController::creerDossier).
            'societe_id'    => ['nullable', 'integer', 'exists:societes,id'],
            'objet'         => ['required', 'string', 'min:10', 'max:500'],
            'valeur'        => ['nullable', 'numeric', 'min:0'],
            'echeance'      => ['nullable', 'date', 'after:today'],
            'urgent'        => ['boolean'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'reviseur_id'   => ['nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Reviseur)],
            'notaire_id'    => ['required', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Notaire)],
            'formaliste_id' => ['nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Formaliste)],
            'donnees'       => ['nullable', 'array'],
            // Brouillon dont ce dossier est l'aboutissement : ses pièces déjà
            // téléversées sont reprises, puis le brouillon est supprimé.
            'brouillon_id'  => ['nullable', 'integer', 'exists:dossier_brouillons,id'],
            ...self::partiesRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'type_acte_id.required'  => 'Le type d\'acte est obligatoire.',
            'type_acte_id.exists'    => 'Le type d\'acte sélectionné n\'existe pas.',
            'objet.required'         => 'L\'objet du dossier est obligatoire.',
            'objet.min'              => 'L\'objet doit contenir au moins 10 caractères.',
            'notaire_id.required'    => 'Le notaire en charge est obligatoire.',
            'echeance.after'         => 'L\'échéance doit être une date future.',
        ];
    }

    /**
     * Règles de validation du tableau `parties`, réutilisées par
     * DossierController::updateQuestionnaire() pour la synchronisation des
     * Partie à l'édition du questionnaire.
     */
    public static function partiesRules(): array
    {
        return [
            'parties'              => ['nullable', 'array'],
            'parties.*.nom'        => ['required_with:parties', 'string', 'max:200'],
            'parties.*.role'       => ['required_with:parties', 'string', 'max:100'],
            'parties.*.partie_id'  => ['nullable', 'integer'],
            'parties.*.type_personne' => ['nullable', 'in:physique,morale'],
            'parties.*.client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            // Emplacement de la partie dans le questionnaire. Émis par le frontend depuis
            // l'origine (partiesPayload.js) et `$fillable` sur le modèle — mais **absent de
            // ces règles**, donc écarté par `validated()` sur lequel travaillent store() et
            // updateQuestionnaire(). Résultat mesuré le 2026-09-24 : les trois colonnes
            // étaient NULL sur les 36 parties en base, `estProjetable()` toujours faux, et
            // tout ClientProjectionService dormant depuis le 2026-08-03 — la décision #33
            // (« la fiche client est la source de vérité de l'identité ») sans effet.
            // Bornes reprises de add_donnees_mapping_to_parties_table.
            'parties.*.donnees_prefixe' => ['nullable', 'string', 'max:30'],
            'parties.*.donnees_bloc'    => ['nullable', 'string', 'max:60'],
            'parties.*.donnees_index'   => ['nullable', 'integer', 'min:0', 'max:65535'],
            'parties.*.cni'        => ['nullable', 'string', 'max:50'],
            'parties.*.telephone'  => ['nullable', 'string', 'max:20'],
            'parties.*.adresse'    => ['nullable', 'string', 'max:500'],
            'parties.*.email'      => ['nullable', 'email', 'max:200'],
            'parties.*.pieces'     => ['nullable', 'array'],
            'parties.*.pieces.*'   => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            // Pièces déjà téléversées dans un brouillon : transmises par catégorie
            // sous forme de clés vers l'état du brouillon, pas de fichiers.
            // { categorie: "brouillons/12/xyz.pdf" }
            'parties.*.pieces_brouillon'   => ['nullable', 'array'],
            'parties.*.pieces_brouillon.*' => ['string', 'max:255'],

            // ── Représentation ───────────────────────────────────────────────
            // Le représentant n'a pas d'id à la création : le lien passe donc par une clé
            // locale, propre à cette soumission, que le serveur résout en seconde passe.
            // **Toujours** par la clé, même en édition où l'id existe : deux chemins de
            // résolution seraient deux branches de validation, et une divergence garantie.
            'parties.*.cle_locale'         => ['nullable', 'string', 'max:64', 'distinct'],
            'parties.*.represente_par_cle' => ['nullable', 'string', 'max:64'],
            'parties.*.representation_motif' => [
                'nullable',
                Rule::enum(MotifRepresentation::class),
                // Déclarer un représentant sans dire pourquoi laisserait l'acte sans clause
                // d'habilitation, et les pièces du titre non réclamées.
                'required_with:parties.*.represente_par_cle',
            ],
            'parties.*.representation_qualite'        => ['nullable', 'string', 'max:100'],
            'parties.*.representation_titre_forme'    => ['nullable', Rule::enum(FormeTitreRepresentation::class)],
            // ⚠️ ISO, et non JJ/MM/AAAA : c'est une **colonne castée**, et le contrat de
            // resources/js/lib/dates.js est formel — « poster du français vers une colonne
            // castée est le défaut à ne pas refaire : PHP y lit un mois/jour américain, et
            // 01/04/1985 devient le 4 janvier sans la moindre erreur ». Le format français
            // reste celui de `donnees`, où la projection réécrit cette date.
            'parties.*.representation_titre_date'     => ['nullable', 'date'],
            'parties.*.representation_titre_autorite' => ['nullable', 'string', 'max:200'],
            'parties.*.representation_titre_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Cohérence des liens de représentation à l'intérieur d'un même payload.
     *
     * Ce que `partiesRules()` ne peut pas exprimer, parce qu'il faut voir le tableau entier.
     * Refuse **bruyamment**, en 422 : une clé inconnue résolue en `null` produirait un dossier
     * où une partie se déclare représentée par personne — un acte annonçant « ici représenté
     * par » suivi de rien.
     *
     * Extraite en méthode statique parce que trois appelants partagent `partiesRules()` :
     * StoreDossierRequest, DossierController::updateQuestionnaire() et
     * DemandeController::convertir(). Une closure recopiée trois fois divergerait.
     */
    public static function validerRepresentations(\Illuminate\Validation\Validator $validator): void
    {
        $parties = $validator->getData()['parties'] ?? [];
        if (! is_array($parties)) {
            return;
        }

        $cles = [];
        foreach ($parties as $i => $partie) {
            if (is_array($partie) && filled($partie['cle_locale'] ?? null)) {
                $cles[$partie['cle_locale']] = $i;
            }
        }

        foreach ($parties as $i => $partie) {
            $cible = is_array($partie) ? ($partie['represente_par_cle'] ?? null) : null;
            if (blank($cible)) {
                continue;
            }

            if (! array_key_exists($cible, $cles)) {
                $validator->errors()->add(
                    "parties.{$i}.represente_par_cle",
                    'Le représentant désigné ne figure pas dans les personnes du dossier.',
                );
                continue;
            }

            if (($partie['cle_locale'] ?? null) === $cible) {
                $validator->errors()->add(
                    "parties.{$i}.represente_par_cle",
                    'Une personne ne peut pas se représenter elle-même.',
                );
                continue;
            }

            // Chaîne de représentation : le mandataire est lui-même représenté. Hors périmètre
            // arbitré, et refusé explicitement plutôt que laissé produire une comparution
            // récursive — « représenté par X, lui-même représenté par Y » — dans un acte
            // authentique.
            if (filled($parties[$cles[$cible]]['represente_par_cle'] ?? null)) {
                $validator->errors()->add(
                    "parties.{$i}.represente_par_cle",
                    'Le représentant désigné est lui-même représenté : une substitution de mandat '
                    . "n'est pas gérée. Désignez une personne qui comparaît en personne.",
                );
            }
        }
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(fn ($v) => self::validerRepresentations($v));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientProjectionService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Recherche de clients pour l'auto-complétion (utilisé par le sélecteur de client
     * pendant la création d'un dossier). Réponse JSON brute, pas une page Inertia.
     */
    public function autocomplete(Request $request)
    {
        $this->authorize('viewAny', Client::class);

        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            // Aucune recherche saisie : proposer les clients les plus récents
            // plutôt que de renvoyer une liste vide, pour permettre de parcourir
            // le répertoire sans avoir à connaître déjà le nom recherché.
            $clients = Client::orderByDesc('created_at')->limit(20)->get();

            return response()->json($clients);
        }

        $clients = Client::query()
            ->where(function ($query) use ($q) {
                // nom_famille ET prenoms : `prenom_nom` est un accessor depuis la
                // séparation des deux champs, il n'est plus interrogeable en SQL.
                $query->where('nom_famille', 'like', "%{$q}%")
                    ->orWhere('prenoms', 'like', "%{$q}%")
                    ->orWhere('denomination', 'like', "%{$q}%")
                    ->orWhere('piece_numero', 'like', "%{$q}%")
                    ->orWhere('rccm', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            })
            ->orderBy('nom_famille')
            ->limit(15)
            ->get();

        return response()->json($clients);
    }

    /**
     * Découpe `prenom_nom` en `nom_famille` + `prenoms` **avant** validation, quand seul
     * l'ancien champ est fourni.
     *
     * Sans cela, `required_if:type,physique` sur `nom_famille` rejetterait tout appel
     * historique — intake public, imports, anciens formulaires — qui n'envoie que
     * `prenom_nom`. Le mutateur du modèle sait déjà découper, mais il intervient trop tard
     * pour la validation.
     */
    private function normaliserIdentite(Request $request): Request
    {
        if ($request->filled('nom_famille') || !$request->filled('prenom_nom')) {
            return $request;
        }

        $mots = preg_split('/\s+/u', trim((string) $request->input('prenom_nom')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$mots) {
            return $request;
        }

        $nom = array_pop($mots);
        $request->merge([
            'nom_famille' => $nom,
            'prenoms'     => implode(' ', $mots),
        ]);

        return $request;
    }

    /**
     * Règles partagées par store() et update() — la fiche client est le même objet
     * qu'on la crée depuis l'assistant de dossier ou qu'on la corrige ensuite.
     */
    /**
     * Messages là où la tournure par défaut se lit mal.
     *
     * `before:today` interpole le littéral « today » (« antérieure au today »), et
     * `prohibited_unless` produit une phrase à double négation. Les autres messages, eux, sont
     * corrects grâce au tableau `attributes` de `lang/fr/validation.php`.
     */
    private function messagesValidation(): array
    {
        return [
            'date_naissance.before'            => 'La date de naissance ne peut pas être dans le futur.',
            'date_naissance.after'             => 'La date de naissance semble erronée (avant 1900).',
            'piece_delivree_le.before_or_equal' => "La pièce ne peut pas avoir été délivrée dans le futur.",
            'piece_delivree_le.after_or_equal' => "La pièce ne peut pas avoir été délivrée avant la naissance du titulaire.",
            'piece_expire_le.after'            => "La date d'expiration doit être postérieure à la date de délivrance.",
            'regime_matrimonial.prohibited_unless' => 'Un régime matrimonial ne se renseigne que pour une personne mariée.',
            'telephone.regex'                  => 'Numéro guinéen attendu — par exemple 622 78 37 32.',
            'representant_legal.required_if'   => "Une personne morale doit avoir un représentant légal : c'est lui qui signe.",
        ];
    }

    private function regles(): array
    {
        return [
            'type'                    => ['required', 'in:physique,morale'],
            'civilite'                => ['nullable', 'string', 'max:10'],
            // Deux champs distincts depuis la règle 4 (nom de famille en majuscules dans
            // les actes). `prenom_nom` reste accepté — le mutateur du modèle le découpe —
            // pour ne pas casser l'intake public ni les imports existants.
            'nom_famille'             => ['required_if:type,physique', 'nullable', 'string', 'max:120'],
            'prenoms'                 => ['nullable', 'string', 'max:120'],
            'prenom_nom'              => ['nullable', 'string', 'max:200'],
            'ne_a'                    => ['nullable', 'string', 'max:100'],

            // ── Cohérence des dates ──────────────────────────────────────────────────
            // La validation ne portait que sur le type (`date`) : une pièce pouvait donc expirer
            // avant d'avoir été délivrée, et une naissance être postérieure à la pièce d'identité.
            'date_naissance'          => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'nationalite'             => ['nullable', 'string', 'max:100'],

            // Un type de pièce sans numéro ne prouve rien, et un numéro sans type ne se vérifie
            // pas : les deux vont ensemble ou pas du tout.
            'piece_type'              => ['nullable', 'string', 'max:100', 'required_with:piece_numero'],
            'piece_numero'            => ['nullable', 'string', 'max:100', 'required_with:piece_type'],

            // Une pièce ne peut pas avoir été délivrée avant la naissance de son porteur, ni dans
            // le futur.
            'piece_delivree_le'       => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_naissance'],
            'piece_delivree_a'        => ['nullable', 'string', 'max:100'],

            // Expirer **après** avoir été délivrée. Une pièce déjà expirée reste enregistrable —
            // l'étude doit pouvoir consigner la situation réelle du client avant de lui demander
            // un renouvellement ; l'avertissement est porté par `avertissements()`.
            'piece_expire_le'         => ['nullable', 'date', 'after:piece_delivree_le'],

            'situation_matrimoniale'  => ['nullable', 'string', 'max:50', 'in:,Célibataire,Marié(e),Divorcé(e),Veuf/Veuve'],

            // Un régime matrimonial n'a de sens que marié : le renseigner sans situation
            // correspondante décrit un état civil impossible, que les actes reprendraient.
            'regime_matrimonial'      => ['nullable', 'string', 'max:100', 'prohibited_unless:situation_matrimoniale,Marié(e)'],
            'denomination'            => ['required_if:type,morale', 'nullable', 'string', 'max:200'],
            'forme'                   => ['nullable', 'string', 'max:50'],
            'rccm'                    => ['nullable', 'string', 'max:100'],
            // On ne fait pas signer une personne morale sans représentant, et l'acte le nomme.
            'representant_legal'      => ['required_if:type,morale', 'nullable', 'string', 'max:200'],
            'representant_qualite'    => ['nullable', 'string', 'max:150'],
            'demeurant_ville'         => ['nullable', 'string', 'max:100'],
            'quartier'                => ['nullable', 'string', 'max:100'],
            'commune'                 => ['nullable', 'string', 'max:100'],
            'pays'                    => ['nullable', 'string', 'max:100'],
            // Mobile guinéen : 6XX XX XX XX, indicatif +224 ou 00224 facultatif, espaces et
            // séparateurs tolérés à la saisie (normalisés à l'enregistrement).
            'telephone'               => ['nullable', 'string', 'max:25', 'regex:/^(?:\+?224|00224)?[\s.-]*6\d{2}(?:[\s.-]*\d{2}){3}$/'],
            'email'                   => ['nullable', 'email', 'max:150'],
            'siege'                   => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Création rapide d'un client depuis le questionnaire de création de dossier.
     * Réponse JSON (le client créé) pour insertion immédiate côté client, sans
     * naviguer hors de l'assistant.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Client::class);

        $client = Client::create($this->normaliserIdentite($request)->validate($this->regles(), $this->messagesValidation()));

        return response()->json($client, 201);
    }

    /**
     * Correction d'une fiche client, sans quitter l'écran d'où on la consulte
     * (assistant de dossier, onglet Informations, Répertoire).
     *
     * La fiche client étant la source de vérité de l'identité, la corriger
     * répercute la nouvelle valeur sur tous les dossiers non clôturés qui la
     * référencent et régénère leurs actes non signés — voir
     * ClientProjectionService. Les dossiers concernés sont renvoyés pour que
     * l'interface puisse le dire explicitement à l'utilisateur : une modification
     * silencieuse de plusieurs dossiers serait inacceptable en notarial.
     */
    public function update(Request $request, Client $client, ClientProjectionService $projection)
    {
        // Effet de bord majeur : reprojette le questionnaire et régénère les actes de
        // tous les dossiers liés non clôturés (voir ClientPolicy).
        $this->authorize('update', $client);

        $client->update($this->normaliserIdentite($request)->validate($this->regles(), $this->messagesValidation()));

        $dossiersMisAJour = $projection->reprojeterDossiersDuClient($client);

        return response()->json([
            ...$client->fresh()->toArray(),
            'dossiers_mis_a_jour' => $dossiersMisAJour,
        ]);
    }
}

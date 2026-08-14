<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Models\ModeleActe;
use App\Models\ModeleCourrier;
use App\Models\TypeActe;
use App\Support\VariantesTypeActe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ModeleActeController extends Controller
{
    // APP_LOCALE=en (config par défaut jamais changée) => les messages de validation
    // Laravel natifs (mimes, max…) sortent en anglais alors que tout le reste de
    // l'app est en français — on ne traduit pas tout Laravel, juste ce qui touche
    // à ce formulaire précis.
    private const FICHIER_MESSAGES = [
        'fichier.required' => 'Veuillez sélectionner un fichier.',
        'fichier.mimes'     => 'Le fichier doit être un vrai .docx (Word 2007+) — un .doc renommé en .docx ne fonctionne pas, il faut l\'ouvrir dans Word et l\'enregistrer sous .docx.',
        'fichier.max'       => 'Le fichier est trop volumineux (20 Mo maximum).',
    ];

    /**
     * Même logique de normalisation de chemin que ActesGeneratorService::genererDocument()
     * (chemin_fichier peut être stocké avec ou sans le préfixe 'modeles/') — sert à repérer
     * dans l'UI un modèle actif dont le fichier a été déplacé/supprimé depuis, avant qu'un
     * dossier ne le découvre au moment de la génération.
     */
    private function fichierExiste(?string $cheminFichier): bool
    {
        if (!$cheminFichier) {
            return false;
        }

        $storagePath = str_starts_with($cheminFichier, 'modeles/') ? $cheminFichier : 'modeles/' . $cheminFichier;

        return Storage::disk('local')->exists($storagePath);
    }

    public function index(Request $request, \App\Services\ActesGeneratorService $generateur)
    {
        $modeles = ModeleActe::with('typeActe', 'typesActes', 'rattachements')
            ->when($request->q, fn ($q, $s) => $q->where('nom', 'like', "%{$s}%")
                ->orWhereHas('typeActe', fn ($q2) => $q2->where('label', 'like', "%{$s}%")))
            ->when($request->categorie, fn ($q, $cat) => $q->whereHas('typeActe', fn ($q2) => $q2->where('categorie', $cat)))
            ->when($request->type_document, fn ($q, $td) => $q->where('type_document', $td))
            ->when($request->statut === 'actif',   fn ($q) => $q->where('est_actif', true))
            ->when($request->statut === 'inactif', fn ($q) => $q->where('est_actif', false))
            ->when($request->sort === 'date', fn ($q) => $q->orderByDesc('updated_at'))
            ->when($request->sort !== 'date', fn ($q) => $q->orderBy('nom'))
            ->get()
            ->map(fn ($m) => [
                'id'             => $m->id,
                'nom'            => $m->nom,
                'type_document'  => $m->type_document,
                'typeDocLabel'   => $m->typeDocumentLabel(),
                'chemin_fichier' => $m->chemin_fichier,
                'fichier_existe' => $this->fichierExiste($m->chemin_fichier),
                'version'        => $m->version,
                'est_actif'      => $m->est_actif,
                                'typeActeLabel'  => $m->typeActe?->label,
                // Un modèle peut servir plusieurs types depuis le 2026-08-11 — c'est le pivot qui
                // décide de l'applicabilité — il n'y a plus de « type d'origine » séparé.
                'applicable_tous'  => $m->applicable_tous,
                'type_acte_ids'    => $m->rattachements->pluck('type_acte_id')->unique()->values(),
                'typesActesLabels' => $m->typesActes->pluck('label'),
                // Rattachements détaillés : c'est la variante qui distingue « sert toutes les
                // modifications » de « ne sert qu'aux cessions de parts ».
                'rattachements'    => $m->rattachements->map(fn ($r) => [
                    'type_acte_id' => $r->type_acte_id,
                    'variante'     => $r->variante,
                ])->values(),
                'roles'            => $m->rolesRemplis(),
                'categorie'      => $m->typeActe?->categorie?->value,
                'categorieLabel' => $m->typeActe?->categorie?->label(),
                'updated_at'     => $m->updated_at?->format('d/m/Y'),
            ]);

        $total     = ModeleActe::count();
        $actifs    = ModeleActe::where('est_actif', true)->count();

        $parCategorie = ModeleActe::with('typeActe')
            ->get()
            ->groupBy(fn ($m) => $m->typeActe?->categorie?->label() ?? 'Autre')
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->toArray();

        $modelesCourriers = ModeleCourrier::with('typesActes')
            ->orderBy('nom')
            ->get()
            ->map(fn (ModeleCourrier $m) => [
                'id'              => $m->id,
                'nom'             => $m->nom,
                'type_document'   => $m->type_document,
                'typeDocLabel'    => $m->typeDocumentLabel(),
                'chemin_fichier'  => $m->chemin_fichier,
                'version'         => $m->version,
                'est_actif'       => $m->est_actif,
                'applicable_tous' => $m->applicable_tous,
                'type_acte_ids'   => $m->typesActes->pluck('id'),
                'typesActesLabels' => $m->typesActes->pluck('label'),
                'updated_at'      => $m->updated_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Modeles/Index', [
            'modeles'          => $modeles,
            'modelesCourriers' => $modelesCourriers,
            // `variantes` accompagne chaque type : c'est ce qui permet à la modale de proposer
            // « restreindre à certaines résolutions » sans redéclarer la nomenclature en JavaScript.
            'typesActes' => TypeActe::orderBy('label')->get(['id', 'code', 'label', 'categorie'])
                ->map(fn (TypeActe $t) => [
                    ...$t->only(['id', 'code', 'label']),
                    'categorie' => $t->categorie?->value,
                    'variantes' => VariantesTypeActe::options($t->code),
                    // Rôles que cette procédure attend — `null` quand elle les accepte tous
                    // (constitutions, ventes, baux). La modale s'en sert pour distinguer les rôles
                    // pertinents des autres : déclarer un rôle qu'aucun type rattaché n'attend est
                    // resté sans effet et sans avertissement jusqu'au 2026-08-11.
                    'roles_attendus' => $generateur->rolesAttendusPour($t),
                ]),
            'categories' => collect(CategorieActe::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            // Vocabulaire des rôles — référence unique, pour ne pas le redéclarer en JavaScript.
            'typesDocument' => ModeleActe::typesDocumentOptions(),
            'filters' => [
                'q'             => $request->q             ?? '',
                'categorie'     => $request->categorie     ?? '',
                'type_document' => $request->type_document ?? '',
                'statut'        => $request->statut        ?? '',
                'sort'          => $request->sort          ?? 'nom',
            ],
            'stats'   => [
                'total'        => $total,
                'actifs'       => $actifs,
                'inactifs'     => $total - $actifs,
                'parCategorie' => $parCategorie,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'nom'                 => ['required', 'string', 'max:200'],
            'type_document'       => ['required', ModeleActe::reglesTypeDocument()],
            'version'             => ['required', 'string', 'max:10'],
            'applicable_tous'     => ['sometimes', 'boolean'],
            // Sans `type_acte_id`, plus rien n'imposait qu'un gabarit serve à quelque chose : un
            // modèle rattaché à aucun type ne serait généré nulle part, sans que rien ne le dise.
            'type_acte_ids'       => ['required_without_all:applicable_tous,rattachements', 'array'],
            'type_acte_ids.*'     => ['exists:types_actes,id'],
            // Rattachements portant une variante — forme complète, `type_acte_ids` restant un
            // raccourci « toutes variantes » pour les appels qui l'ignorent.
            'rattachements'            => ['sometimes', 'array'],
            'rattachements.*.type_acte_id' => ['required', 'exists:types_actes,id'],
            'rattachements.*.variante'     => ['nullable', 'string', 'max:60'],
            'roles'               => ['sometimes', 'array'],
            'roles.*'             => ['string', ModeleActe::reglesTypeDocument()],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['required', 'string', 'max:500'];
        }

        $data = $request->validate($rules, self::FICHIER_MESSAGES);
        $rattachements = $this->rattachementsDemandes($data);
        $roles         = $data['roles'] ?? [];
        unset($data['type_acte_ids'], $data['rattachements'], $data['roles']);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles', $filename, 'local');
            $data['chemin_fichier'] = $filename;
            unset($data['fichier']);
        }

        $modele = ModeleActe::create(array_merge($data, [
            'est_actif'  => true,
            'updated_by' => Auth::id(),
        ]));

        $this->ecrireRattachements($modele, $rattachements);
        $modele->definirRoles($roles !== [] ? $roles : [$modele->type_document]);

        return back()->with('success', 'Modèle créé avec succès.');
    }

    /**
     * Types auxquels le modèle s'applique.
     *
     * Repli sur le type d'origine quand le formulaire ne les envoie pas : un appel qui ne connaît
     * pas encore le partage (ancien client, seeder, import) ne doit pas délier le modèle de son
     * type et couper la génération d'actes.
     *
     * @param  array<string, mixed> $data
     * @return array<int, int>
     */
    private function rattachementsDemandes(array $data, ?ModeleActe $modele = null): array
    {
        // Forme complète, avec variantes.
        if (array_key_exists('rattachements', $data)) {
            return array_map(
                fn (array $r) => ['type_acte_id' => (int) $r['type_acte_id'], 'variante' => $r['variante'] ?? null],
                $data['rattachements'] ?? [],
            );
        }

        // Raccourci « toutes variantes ».
        if (array_key_exists('type_acte_ids', $data)) {
            return array_map(
                fn ($id) => ['type_acte_id' => (int) $id, 'variante' => null],
                $data['type_acte_ids'] ?? [],
            );
        }

        // Aucun des deux : on ne devine plus rien. `type_acte_id` a disparu, et inventer un
        // rattachement rendrait le gabarit applicable à un type que personne n'a coché.
        return [];
    }

    /** @param array<int, array{type_acte_id: int, variante: ?string}> $rattachements */
    private function ecrireRattachements(ModeleActe $modele, array $rattachements): void
    {
        $modele->rattachements()->delete();

        foreach ($rattachements as $r) {
            $modele->rattachements()->create($r);
        }
    }

    public function update(Request $request, ModeleActe $modele)
    {
        $rules = [
            'nom'                 => ['sometimes', 'string', 'max:200'],
            'type_document'       => ['sometimes', ModeleActe::reglesTypeDocument()],
            'applicable_tous'     => ['sometimes', 'boolean'],
            'type_acte_ids'       => ['sometimes', 'array'],
            'type_acte_ids.*'     => ['exists:types_actes,id'],
            // Rattachements portant une variante — forme complète, `type_acte_ids` restant un
            // raccourci « toutes variantes » pour les appels qui l'ignorent.
            'rattachements'            => ['sometimes', 'array'],
            'rattachements.*.type_acte_id' => ['required', 'exists:types_actes,id'],
            'rattachements.*.variante'     => ['nullable', 'string', 'max:60'],
            'roles'               => ['sometimes', 'array'],
            'roles.*'             => ['string', ModeleActe::reglesTypeDocument()],
            'version'             => ['sometimes', 'string', 'max:10'],
            'est_actif'           => ['sometimes', 'boolean'],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['sometimes', 'string', 'max:500'];
        }

        $data = $request->validate($rules, self::FICHIER_MESSAGES);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles', $filename, 'local');
            $data['chemin_fichier'] = $filename;
            unset($data['fichier']);

            $ancienChemin = $modele->chemin_fichier
                ? (str_starts_with($modele->chemin_fichier, 'modeles/') ? $modele->chemin_fichier : 'modeles/' . $modele->chemin_fichier)
                : null;
            if ($ancienChemin && Storage::disk('local')->exists($ancienChemin)) {
                Storage::disk('local')->delete($ancienChemin);
            }
        }

        $rattachements = $this->rattachementsDemandes($data, $modele);
        $roles         = array_key_exists('roles', $data) ? $data['roles'] : null;
        unset($data['type_acte_ids'], $data['rattachements'], $data['roles']);

        $modele->update(array_merge($data, ['updated_by' => Auth::id()]));

        // Les rattachements sont la seule source de vérité de l'applicabilité ; l'ancien
        // type d'origine et suit le premier rattachement, pour que l'affichage et les seeders
        // continuent de fonctionner.
        $this->ecrireRattachements($modele, $rattachements);

        if ($roles !== null) {
            $modele->definirRoles($roles !== [] ? $roles : [$modele->type_document]);
        }

        return back()->with('success', 'Modèle mis à jour.');
    }

    public function dupliquer(ModeleActe $modele)
    {
        $copie = ModeleActe::create([
            'nom'            => 'Copie de ' . $modele->nom,
            'type_document'  => $modele->type_document,
            'chemin_fichier' => $modele->chemin_fichier,
            'version'        => '1.0',
            'est_actif'      => false,
            'updated_by'     => Auth::id(),
            'applicable_tous' => $modele->applicable_tous,
        ]);

        // Rattachements et rôles recopiés : l'applicabilité ne vit plus dans une colonne du modèle.
        // Sans cette reprise, la copie ne serait générée nulle part — un « duplicata » qui ne
        // duplique pas ce qui compte.
        foreach ($modele->rattachements as $r) {
            $copie->rattachements()->create(['type_acte_id' => $r->type_acte_id, 'variante' => $r->variante]);
        }

        $copie->definirRoles($modele->rolesRemplis());

        return back()->with('success', 'Modèle dupliqué — pensez à le renommer.');
    }

    public function destroy(ModeleActe $modele)
    {
        $modele->delete();

        return back()->with('success', 'Modèle supprimé.');
    }
}

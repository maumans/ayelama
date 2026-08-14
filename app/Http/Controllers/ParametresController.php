<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Models\Bareme;
use App\Models\DocumentAttendu;
use App\Models\ModeleActe;
use App\Models\ModeleCourrier;
use App\Models\Setting;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Support\VariantesTypeActe;
use Database\Seeders\ReglesGestionDocumentsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ParametresController extends Controller
{
    public function index()
    {
        $parRole = UserRole::selectRaw('role, count(*) as n')
            ->groupBy('role')
            ->get()
            ->map(fn ($r) => ['role' => $r->getRawOriginal('role'), 'count' => (int) $r->n])
            ->values();

        $parCategorie = TypeActe::selectRaw('categorie, count(*) as n')
            ->groupBy('categorie')
            ->get()
            ->map(fn ($t) => [
                'categorie' => $t->categorie?->value,
                'label'     => $t->categorie?->label(),
                'count'     => (int) $t->n,
            ])
            ->values();

        // Données Utilisateurs
        $utilisateurs = User::with('roles')->orderBy('name')->get()->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'roles'      => $u->roleValues(),
            'roleLabels' => $u->roleLabels(),
            'initiales'  => $u->initiales,
            'telephone'  => $u->telephone,
            'actif'      => $u->actif,
            'created_at' => $u->created_at?->format('d/m/Y'),
        ]);

        $roles = collect(RoleUtilisateur::cases())->map(fn ($r) => [
            'value' => $r->value,
            'label' => $r->label(),
        ]);

        // Données Types d'actes
        $typesActes = TypeActe::orderBy('categorie')->orderBy('label')->get()->map(fn ($t) => [
            'id'             => $t->id,
            'code'           => $t->code,
            'label'          => $t->label,
            'categorie'      => $t->categorie?->value,
            'categorieLabel' => $t->categorie?->label(),
            'delai_jours'    => $t->delai_jours,
            'actif'          => $t->actif,
            'description'    => $t->description,
        ]);

        $settings = array_merge(
            $this->apparenceDefaults(),
            Setting::all()->toArray()
        );
        $settings['logo_url'] = $settings['logo_path']
            ? url('storage/' . $settings['logo_path'])
            : null;

        return Inertia::render('Parametres/Index', [
            'stats' => [
                'utilisateurs'       => User::count(),
                'utilisateursActifs' => User::where('actif', true)->count(),
                'typesActes'         => TypeActe::count(),
                'typesActifs'        => TypeActe::where('actif', true)->count(),
                'baremes'            => Bareme::count(),
                'baremesActifs'      => Bareme::where('actif', true)->count(),
                'typesAvecBaremes'   => TypeActe::has('baremes')->count(),
            ],
            'parRole'           => $parRole,
            'parCategorie'      => $parCategorie,
            'utilisateurs'      => $utilisateurs,
            'roles'             => $roles,
            'typesActes'        => $typesActes,
            'apparence'         => $settings,
            'securite'          => $this->securiteSettings(),
            'defauts'           => Setting::defaultAssignees(),
        ]);
    }

    public function utilisateurs()
    {
        return Inertia::render('Parametres/Utilisateurs', [
            'utilisateurs' => User::with('roles')->orderBy('name')->get()->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'roles'      => $u->roleValues(),
                'roleLabels' => $u->roleLabels(),
                'initiales'  => $u->initiales,
                'telephone'  => $u->telephone,
                'actif'      => $u->actif,
                'created_at' => $u->created_at?->format('d/m/Y'),
            ]),
            'roles' => collect(RoleUtilisateur::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function storeUtilisateur(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:200'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', Password::defaults()],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string', 'in:' . implode(',', array_column(RoleUtilisateur::cases(), 'value'))],
            'initiales' => ['nullable', 'string', 'max:5'],
            'telephone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'initiales' => $data['initiales'] ?? strtoupper(substr($data['name'], 0, 2)),
            'telephone' => $data['telephone'] ?? null,
            'actif'     => true,
        ]);
        $user->syncRoles($data['roles']);

        return back()->with('success', 'Utilisateur créé.');
    }

    public function updateUtilisateur(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:200'],
            'email'     => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['sometimes', 'nullable', Password::defaults()],
            'roles'     => ['sometimes', 'array', 'min:1'],
            'roles.*'   => ['string', 'in:' . implode(',', array_column(RoleUtilisateur::cases(), 'value'))],
            'initiales' => ['sometimes', 'string', 'max:5'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'actif'     => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);
        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function typesActes()
    {
        $modeles = ModeleActe::with('rattachements')->get();

        return Inertia::render('Parametres/TypesActes', [
            'typesActes' => TypeActe::orderBy('categorie')->orderBy('label')->get()->map(fn ($t) => [
                'id'             => $t->id,
                'code'           => $t->code,
                'label'          => $t->label,
                'categorie'      => $t->categorie?->value,
                'categorieLabel' => $t->categorie?->label(),
                'delai_jours'    => $t->delai_jours,
                'actif'          => $t->actif,
                'description'    => $t->description,
                // La carte du processus : ce que la procédure attend, et ce qui peut le produire.
                'processus'      => $this->processus($t, $modeles),
            ]),
            'typesDocument' => ModeleActe::typesDocumentOptions(),
        ]);
    }

    /**
     * Couverture du processus d'un type d'acte : ses variantes, leurs documents attendus, et le
     * gabarit qui remplit chacun — ou son absence.
     *
     * C'est ce que l'étude n'avait nulle part : jusqu'ici, un gabarit manquant se découvrait en
     * générant un dossier, et le message d'erreur ne disait pas lequel. Le compteur d'incomplets
     * donne l'état de la configuration **avant** d'ouvrir un dossier.
     *
     * @param  \Illuminate\Support\Collection<int, ModeleActe> $modeles
     */
    private function processus(TypeActe $typeActe, $modeles): array
    {
        $variantes = VariantesTypeActe::options($typeActe->code);

        // Un type d'acte sans variante n'a pas de liste imposée : ses actes sont ceux de ses
        // gabarits actifs. On ne lui invente pas de procédure — même raisonnement que l'abandon des
        // « documents obligatoires configurés » pour la clôture.
        if ($variantes === []) {
            return [
                'seDecline'  => false,
                'variantes'  => [],
                'incomplets' => 0,
                'gabarits'   => $modeles->where('est_actif', true)
                    ->filter(fn (ModeleActe $m) => $m->applicablePour($typeActe))
                    ->map(fn (ModeleActe $m) => ['id' => $m->id, 'nom' => $m->nom, 'roles' => $m->rolesRemplis()])
                    ->values(),
            ];
        }

        $lignes     = [];
        $incomplets = 0;

        foreach ($variantes as $variante) {
            $attendus = DocumentAttendu::pourProcedure($typeActe->id, $variante['valeur'])->get();

            // Aucune configuration : on montre la référence légale, pour que l'écran soit lisible
            // avant même que le seeder ait tourné.
            $reference = DocumentAttendu::reference($typeActe->code, $variante['valeur']) ?? [];
            $slugs     = $attendus->isNotEmpty()
                ? $attendus->pluck('type_document')->all()
                : array_keys($reference);

            $documents = [];
            foreach ($slugs as $slug) {
                $gabarit = $modeles->where('est_actif', true)->first(
                    fn (ModeleActe $m) => $m->remplitRole($slug)
                        && $m->applicablePour($typeActe, $variante['valeur']),
                );

                // Les statuts se produisent aussi depuis ceux de la société, sans aucun gabarit —
                // ne pas les compter comme manquants serait mentir, les compter aussi.
                $heriteDeLaSociete = $slug === 'statuts_maj';

                $documents[] = [
                    'slug'    => $slug,
                    'label'   => ModeleActe::TYPES_DOCUMENT[$slug] ?? ($reference[$slug] ?? $slug),
                    'gabarit' => $gabarit ? ['id' => $gabarit->id, 'nom' => $gabarit->nom] : null,
                    'source'  => $gabarit
                        ? 'modèle « ' . $gabarit->nom . ' »'
                        : ($heriteDeLaSociete ? 'statuts en vigueur de la société' : null),
                ];

                if (!$gabarit && !$heriteDeLaSociete) {
                    $incomplets++;
                }
            }

            $lignes[] = [
                ...$variante,
                'documents' => $documents,
                'complet'   => collect($documents)->every(fn (array $d) => $d['gabarit'] || $d['source']),
                // Écart à la règle écrite : une configuration modifiée doit se voir, puisqu'elle
                // décide du contenu d'actes authentiques.
                'diverge'   => DocumentAttendu::divergeDeLaReference($typeActe->code, $variante['valeur'], $slugs),
                'reference' => array_keys($reference),
            ];
        }

        return [
            'seDecline'  => true,
            'variantes'  => $lignes,
            'incomplets' => $incomplets,
            'gabarits'   => [],
        ];
    }

    /** Ajoute ou retire un document attendu d'une procédure. */
    public function updateDocumentsAttendus(Request $request, TypeActe $typeActe)
    {
        $data = $request->validate([
            'variante'        => ['nullable', 'string', Rule::in(VariantesTypeActe::valeurs($typeActe->code))],
            'type_documents'  => ['present', 'array'],
            'type_documents.*' => ['string', Rule::in(array_keys(ModeleActe::TYPES_DOCUMENT))],
        ]);

        $variante = $data['variante'] ?? null;
        $avant    = DocumentAttendu::pourProcedure($typeActe->id, $variante)->pluck('type_document')->all();

        DocumentAttendu::where('type_acte_id', $typeActe->id)->where('variante', $variante)->delete();

        $ordre = 0;
        foreach ($data['type_documents'] as $slug) {
            DocumentAttendu::create([
                'type_acte_id'  => $typeActe->id,
                'variante'      => $variante,
                'type_document' => $slug,
                'obligatoire'   => true,
                'ordre'         => $ordre++,
            ]);
        }

        // Une règle qui décide du contenu d'actes authentiques ne change pas en silence.
        Log::info('Documents attendus modifiés', [
            'type_acte' => $typeActe->code,
            'variante'  => $variante,
            'avant'     => $avant,
            'apres'     => $data['type_documents'],
            'par'       => auth()->id(),
        ]);

        return back()->with('success', 'Documents attendus mis à jour.');
    }

    /** Restaure la liste écrite au CR de juillet 2026 pour une procédure. */
    public function reinitialiserDocumentsAttendus(Request $request, TypeActe $typeActe)
    {
        $data = $request->validate([
            'variante' => ['nullable', 'string', Rule::in(VariantesTypeActe::valeurs($typeActe->code))],
        ]);

        ReglesGestionDocumentsSeeder::reinitialiser($typeActe, $data['variante'] ?? null);

        Log::info('Documents attendus réinitialisés à la référence', [
            'type_acte' => $typeActe->code,
            'variante'  => $data['variante'] ?? null,
            'par'       => auth()->id(),
        ]);

        return back()->with('success', 'Liste restaurée à la référence légale.');
    }

    public function updateTypeActe(Request $request, TypeActe $typeActe)
    {
        $data = $request->validate([
            'label'       => ['sometimes', 'string', 'max:200'],
            'delai_jours' => ['sometimes', 'integer', 'min:1'],
            'actif'       => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $typeActe->update($data);

        return back()->with('success', 'Type d\'acte mis à jour.');
    }

    // ── Barèmes (facturation + génération automatique de formalités) ───────

    private const ORGANISMES = ['APIP', 'Impots', 'Conservation', 'CNSS', 'Greffe', 'Notaire', 'Autre'];

    private function baremeToArray(Bareme $b): array
    {
        return [
            'id'                  => $b->id,
            'organisme'           => $b->organisme,
            'libelle'             => $b->libelle,
            'taux'                => $b->taux,
            'montant_fixe'        => $b->montant_fixe,
            'quantite_defaut'     => $b->quantite_defaut ?? 1,
            'base_calcul'         => $b->base_calcul,
            'description'         => $b->description,
            'actif'               => $b->actif,
            'ordre'               => $b->ordre,
            'genere_formalite'    => $b->genere_formalite,
            'depend_de_bareme_id' => $b->depend_de_bareme_id,
            'type_impot'          => $b->type_impot,
            'retour_attendu'      => $b->retour_attendu,
            'delai_heures'        => $b->delai_heures,
            'pieces_requises'     => $b->pieces_requises ?? [],
        ];
    }

    public function baremes(Request $request)
    {
        $typesActes = TypeActe::with(['baremes' => fn ($q) => $q->orderBy('ordre')->orderBy('organisme')])
            ->when($request->categorie, fn ($q, $cat) => $q->where('categorie', $cat))
            ->orderBy('categorie')
            ->orderBy('label')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'label'          => $t->label,
                'categorie'      => $t->categorie?->value,
                'categorieLabel' => $t->categorie?->label(),
                'baremes'        => $t->baremes->map(fn ($b) => $this->baremeToArray($b))->values(),
            ]);

        return Inertia::render('Parametres/Baremes', [
            'typesActes' => $typesActes,
            'categories' => collect(CategorieActe::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'organismes' => self::ORGANISMES,
            'filters'    => $request->only(['categorie']),
            'stats'      => [
                'total'  => Bareme::count(),
                'actifs' => Bareme::where('actif', true)->count(),
            ],
        ]);
    }

    /**
     * Vérifie que le barème "dépend de" appartient bien au même type d'acte
     * (une démarche ne peut être bloquée que par une autre démarche du même type d'acte).
     */
    private function assertDependanceMemeTypeActe(int $typeActeId, ?int $dependDeBaremeId): void
    {
        if (!$dependDeBaremeId) {
            return;
        }

        $valide = Bareme::where('id', $dependDeBaremeId)
            ->where('type_acte_id', $typeActeId)
            ->where('genere_formalite', true)
            ->exists();

        if (!$valide) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'depend_de_bareme_id' => ["La démarche dont dépend celle-ci doit appartenir au même type d'acte et générer elle-même une formalité."],
            ]);
        }
    }

    public function storeBareme(Request $request)
    {
        $data = $request->validate([
            'applicable_tous'   => ['required', 'boolean'],
            'type_acte_ids'     => ['required_if:applicable_tous,false', 'array'],
            'type_acte_ids.*'   => ['exists:types_actes,id'],
            'organisme'         => ['required', 'string', 'max:100'],
            'libelle'           => ['required', 'string', 'max:200'],
            'taux'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_fixe'      => ['nullable', 'numeric', 'min:0'],
            'quantite_defaut'   => ['nullable', 'integer', 'min:1'],
            'base_calcul'       => ['required', 'in:valeur_acte,montant_fixe'],
            'description'       => ['nullable', 'string'],
            'genere_formalite'    => ['boolean'],
            'depend_de_bareme_id' => ['nullable', 'integer', 'exists:baremes,id'],
            'type_impot'        => ['nullable', 'string', 'max:100'],
            'retour_attendu'    => ['nullable', 'string', 'max:200'],
            'delai_heures'      => ['nullable', 'integer', 'min:1'],
            'pieces_requises'   => ['nullable', 'array'],
            'pieces_requises.*' => ['string', 'max:200'],
        ]);

        $typeActeIds = $data['applicable_tous'] ? TypeActe::pluck('id')->all() : $data['type_acte_ids'];
        unset($data['applicable_tous'], $data['type_acte_ids']);

        foreach ($typeActeIds as $typeActeId) {
            $this->assertDependanceMemeTypeActe($typeActeId, $data['depend_de_bareme_id'] ?? null);
        }

        foreach ($typeActeIds as $typeActeId) {
            Bareme::create(array_merge($data, [
                'type_acte_id' => $typeActeId,
            ]));
        }

        $count = count($typeActeIds);
        return back()->with('success', $count > 1 ? "{$count} barèmes créés." : 'Barème créé.');
    }

    public function updateBareme(Request $request, Bareme $bareme)
    {
        $data = $request->validate([
            'organisme'         => ['sometimes', 'string', 'max:100'],
            'libelle'           => ['sometimes', 'string', 'max:200'],
            'taux'              => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'montant_fixe'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantite_defaut'   => ['sometimes', 'nullable', 'integer', 'min:1'],
            'base_calcul'       => ['sometimes', 'in:valeur_acte,montant_fixe'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'actif'             => ['sometimes', 'boolean'],
            'ordre'             => ['sometimes', 'integer', 'min:0'],
            'genere_formalite'    => ['sometimes', 'boolean'],
            'depend_de_bareme_id' => ['sometimes', 'nullable', 'integer', 'exists:baremes,id'],
            'type_impot'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'retour_attendu'    => ['sometimes', 'nullable', 'string', 'max:200'],
            'delai_heures'      => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pieces_requises'   => ['sometimes', 'nullable', 'array'],
            'pieces_requises.*' => ['string', 'max:200'],
        ]);

        if (array_key_exists('depend_de_bareme_id', $data) && $data['depend_de_bareme_id'] === (int) $bareme->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'depend_de_bareme_id' => ["Une démarche ne peut pas dépendre d'elle-même."],
            ]);
        }

        if (array_key_exists('depend_de_bareme_id', $data)) {
            $this->assertDependanceMemeTypeActe($bareme->type_acte_id, $data['depend_de_bareme_id']);
        }

        $bareme->update($data);

        return back()->with('success', 'Barème mis à jour.');
    }

    public function destroyBareme(Bareme $bareme)
    {
        $bareme->delete();

        return back()->with('success', 'Barème supprimé.');
    }

    // ── Types d'actes : création ──────────────────────────────────────────

    public function storeTypeActe(Request $request)
    {
        $data = $request->validate([
            'label'       => ['required', 'string', 'max:200'],
            'code'        => ['required', 'string', 'max:30', 'unique:types_actes,code', 'regex:/^[A-Z0-9\-]+$/'],
            'categorie'   => ['required', 'string', 'in:' . implode(',', array_column(CategorieActe::cases(), 'value'))],
            'delai_jours' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $cat = CategorieActe::from($data['categorie']);

        TypeActe::create([
            'label'              => $data['label'],
            'code'               => strtoupper($data['code']),
            'categorie'          => $cat,
            'prefixe_reference'  => $cat->prefixeReference(),
            'delai_jours'        => $data['delai_jours'],
            'description'        => $data['description'] ?? null,
            'actif'              => true,
            'ordre'              => 0,
        ]);

        return back()->with('success', "Type d'acte créé.");
    }

    // ── Apparence ─────────────────────────────────────────────────────────

    private function apparenceDefaults(): array
    {
        return [
            'office_nom'        => 'Maître Ayelama Bah',
            'office_sous_titre' => 'Notaire',
            'couleur_primaire'  => '#0F2D60',
            'couleur_accent'    => '#E8A520',
            'couleur_fond'      => '#F5F5F3',
            'logo_path'         => null,
        ];
    }

    public function apparence()
    {
        $settings = array_merge(
            $this->apparenceDefaults(),
            Setting::all()->toArray()
        );

        $settings['logo_url'] = $settings['logo_path']
            ? url('storage/' . $settings['logo_path'])
            : null;

        return Inertia::render('Parametres/Index', $this->buildIndexData([
            'apparence' => $settings,
        ]));
    }

    public function updateApparence(Request $request)
    {
        $data = $request->validate([
            'office_nom'        => ['required', 'string', 'max:100'],
            'office_sous_titre' => ['required', 'string', 'max:60'],
            'couleur_primaire'  => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'couleur_accent'    => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'couleur_fond'      => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Setting::setMany($data);

        return back()->with('success', 'Apparence mise à jour.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $old = Setting::get('logo_path');
        if ($old) Storage::disk('public')->delete($old);

        $path = $request->file('logo')->store('logos', 'public');
        Setting::set('logo_path', $path);

        return back()->with('success', 'Logo mis à jour.');
    }

    public function deleteLogo()
    {
        $path = Setting::get('logo_path');
        if ($path) Storage::disk('public')->delete($path);
        Setting::set('logo_path', null);

        return back()->with('success', 'Logo supprimé.');
    }

    // ── Sécurité (OTP / 2FA) ──────────────────────────────────────────────

    private function securiteDefaults(): array
    {
        return [
            'otp_enabled'          => false,
            'otp_duration_minutes' => 10,
        ];
    }

    private function securiteSettings(): array
    {
        $settings = array_merge($this->securiteDefaults(), Setting::all()->toArray());
        $settings['otp_enabled'] = (bool) $settings['otp_enabled'];
        $settings['otp_duration_minutes'] = (int) $settings['otp_duration_minutes'];

        return $settings;
    }

    public function securite()
    {
        return Inertia::render('Parametres/Index', $this->buildIndexData([
            'securite' => $this->securiteSettings(),
        ]));
    }

    public function updateSecurite(Request $request)
    {
        $data = $request->validate([
            'otp_enabled'          => ['required', 'boolean'],
            'otp_duration_minutes' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        Setting::setMany($data);

        return back()->with('success', 'Paramètres de sécurité mis à jour.');
    }

    // ── Assignations par défaut (notaire/réviseur/formaliste pré-sélectionnés) ────

    public function updateDefauts(Request $request)
    {
        $data = $request->validate([
            'default_notaire_id'    => ['nullable', 'integer', Rule::exists('users', 'id')],
            'default_reviseur_id'   => ['nullable', 'integer', Rule::exists('users', 'id')],
            'default_formaliste_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        Setting::setMany($data);

        return back()->with('success', 'Assignations par défaut mises à jour.');
    }

    // ── Helper commun pour index() et apparence() ─────────────────────────

    private function buildIndexData(array $extra = []): array
    {
        $settings = array_merge(
            $this->apparenceDefaults(),
            Setting::all()->toArray()
        );
        $settings['logo_url'] = $settings['logo_path']
            ? url('storage/' . $settings['logo_path'])
            : null;

        return array_merge([
            'apparence' => $settings,
            'securite'  => $this->securiteSettings(),
            'defauts'   => Setting::defaultAssignees(),
        ], $extra);
    }
}

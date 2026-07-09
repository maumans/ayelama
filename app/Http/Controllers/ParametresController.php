<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Models\Bareme;
use App\Models\RevisionGrille;
use App\Models\Setting;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        // Données Barèmes
        $baremesTypesActes = TypeActe::with(['baremes' => fn ($q) => $q->orderBy('ordre')->orderBy('organisme')])
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

        $categories = collect(CategorieActe::cases())->map(fn ($c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ]);

        $grilles = RevisionGrille::with('typeActe')
            ->orderBy('type_acte_id')
            ->get()
            ->map(fn ($g) => [
                'id'            => $g->id,
                'type_acte_id'  => $g->type_acte_id,
                'typeActeLabel' => $g->typeActe?->label,
                'typeActeCat'   => $g->typeActe?->categorie?->value,
                'version'       => $g->version,
                'est_active'    => $g->est_active,
                'points'        => $g->points ?? [],
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
                'grillesActives'     => RevisionGrille::where('est_active', true)->count(),
            ],
            'parRole'           => $parRole,
            'parCategorie'      => $parCategorie,
            'utilisateurs'      => $utilisateurs,
            'roles'             => $roles,
            'typesActes'        => $typesActes,
            'baremesTypesActes' => $baremesTypesActes,
            'categories'        => $categories,
            'organismes'        => self::ORGANISMES,
            'grilles'           => $grilles,
            'apparence'         => $settings,
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
            'password'  => ['required', 'string', 'min:8'],
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
            'password'  => ['sometimes', 'nullable', 'string', 'min:8'],
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
            ]),
        ]);
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

    private const ORGANISMES = ['APIP', 'Impots', 'Conservation', 'CNSS', 'Notaire', 'Autre'];

    private function baremeToArray(Bareme $b): array
    {
        return [
            'id'               => $b->id,
            'organisme'        => $b->organisme,
            'libelle'          => $b->libelle,
            'taux'             => $b->taux,
            'montant_fixe'     => $b->montant_fixe,
            'base_calcul'      => $b->base_calcul,
            'description'      => $b->description,
            'actif'            => $b->actif,
            'ordre'            => $b->ordre,
            'genere_formalite' => $b->genere_formalite,
            'obligatoire'      => $b->obligatoire,
            'type_impot'       => $b->type_impot,
            'retour_attendu'   => $b->retour_attendu,
            'delai_heures'     => $b->delai_heures,
            'pieces_requises'  => $b->pieces_requises ?? [],
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
     * Vérifie qu'aucun autre Bareme du même type d'acte + organisme ne génère déjà
     * une formalité (une seule Formalite possible par organisme et par dossier).
     */
    private function assertOrganismeFormaliteDisponible(int $typeActeId, string $organisme, ?int $ignoreId = null): void
    {
        $conflit = Bareme::where('type_acte_id', $typeActeId)
            ->where('organisme', $organisme)
            ->where('genere_formalite', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($conflit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'genere_formalite' => ["Une formalité est déjà générée pour l'organisme « {$organisme} » sur ce type d'acte."],
            ]);
        }
    }

    public function storeBareme(Request $request)
    {
        $data = $request->validate([
            'type_acte_id'      => ['required', 'exists:types_actes,id'],
            'organisme'         => ['required', 'string', 'max:100'],
            'libelle'           => ['required', 'string', 'max:200'],
            'taux'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_fixe'      => ['nullable', 'numeric', 'min:0'],
            'base_calcul'       => ['required', 'in:valeur_acte,montant_fixe'],
            'description'       => ['nullable', 'string'],
            'genere_formalite'  => ['boolean'],
            'obligatoire'       => ['boolean'],
            'type_impot'        => ['nullable', 'string', 'max:100'],
            'retour_attendu'    => ['nullable', 'string', 'max:200'],
            'delai_heures'      => ['nullable', 'integer', 'min:1'],
            'pieces_requises'   => ['nullable', 'array'],
            'pieces_requises.*' => ['string', 'max:200'],
        ]);

        if ($data['genere_formalite'] ?? false) {
            $this->assertOrganismeFormaliteDisponible($data['type_acte_id'], $data['organisme']);
        }

        Bareme::create(array_merge($data, [
            'obligatoire' => $data['obligatoire'] ?? true,
        ]));

        return back()->with('success', 'Barème créé.');
    }

    public function updateBareme(Request $request, Bareme $bareme)
    {
        $data = $request->validate([
            'organisme'         => ['sometimes', 'string', 'max:100'],
            'libelle'           => ['sometimes', 'string', 'max:200'],
            'taux'              => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'montant_fixe'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'base_calcul'       => ['sometimes', 'in:valeur_acte,montant_fixe'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'actif'             => ['sometimes', 'boolean'],
            'ordre'             => ['sometimes', 'integer', 'min:0'],
            'genere_formalite'  => ['sometimes', 'boolean'],
            'obligatoire'       => ['sometimes', 'boolean'],
            'type_impot'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'retour_attendu'    => ['sometimes', 'nullable', 'string', 'max:200'],
            'delai_heures'      => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pieces_requises'   => ['sometimes', 'nullable', 'array'],
            'pieces_requises.*' => ['string', 'max:200'],
        ]);

        if ($data['genere_formalite'] ?? false) {
            $organisme = $data['organisme'] ?? $bareme->organisme;
            $this->assertOrganismeFormaliteDisponible($bareme->type_acte_id, $organisme, $bareme->id);
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

    // ── Grilles de révision ───────────────────────────────────────────────

    public function grilles()
    {
        $typesActes = TypeActe::orderBy('categorie')->orderBy('label')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'categorie' => $t->categorie?->value]);

        $grilles = RevisionGrille::with('typeActe')->get()->map(fn ($g) => [
            'id'            => $g->id,
            'type_acte_id'  => $g->type_acte_id,
            'typeActeLabel' => $g->typeActe?->label,
            'version'       => $g->version,
            'est_active'    => $g->est_active,
            'points'        => $g->points ?? [],
        ]);

        return Inertia::render('Parametres/Grilles', [
            'grilles'    => $grilles,
            'typesActes' => $typesActes,
        ]);
    }

    public function storeGrille(Request $request)
    {
        $data = $request->validate([
            'type_acte_id' => ['required', 'exists:types_actes,id'],
            'version'      => ['required', 'string', 'max:10'],
            'points'       => ['required', 'array', 'min:1'],
            'points.*.groupe' => ['required', 'string', 'max:100'],
            'points.*.label'  => ['required', 'string', 'max:300'],
            'points.*.piece'  => ['nullable', 'string', 'max:200'],
            'points.*.requis' => ['boolean'],
        ]);

        $points = collect($data['points'])->map(fn ($p, $i) => array_merge([
            'id'     => Str::uuid()->toString(),
            'piece'  => null,
            'requis' => true,
        ], $p))->values()->all();

        // Désactiver les anciennes grilles du même type
        RevisionGrille::where('type_acte_id', $data['type_acte_id'])->update(['est_active' => false]);

        RevisionGrille::create([
            'type_acte_id' => $data['type_acte_id'],
            'version'      => $data['version'],
            'est_active'   => true,
            'points'       => $points,
        ]);

        return back()->with('success', 'Grille créée.');
    }

    public function updateGrille(Request $request, RevisionGrille $grille)
    {
        $data = $request->validate([
            'version'         => ['sometimes', 'string', 'max:10'],
            'est_active'      => ['sometimes', 'boolean'],
            'points'          => ['sometimes', 'array'],
            'points.*.id'     => ['required', 'string'],
            'points.*.groupe' => ['required', 'string', 'max:100'],
            'points.*.label'  => ['required', 'string', 'max:300'],
            'points.*.piece'  => ['nullable', 'string', 'max:200'],
            'points.*.requis' => ['boolean'],
        ]);

        if (isset($data['est_active']) && $data['est_active']) {
            RevisionGrille::where('type_acte_id', $grille->type_acte_id)
                ->where('id', '!=', $grille->id)
                ->update(['est_active' => false]);
        }

        $grille->update($data);

        return back()->with('success', 'Grille mise à jour.');
    }

    public function destroyGrille(RevisionGrille $grille)
    {
        $grille->delete();

        return back()->with('success', 'Grille supprimée.');
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
        ], $extra);
    }
}

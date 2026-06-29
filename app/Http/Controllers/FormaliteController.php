<?php

namespace App\Http\Controllers;

use App\Enums\StatutFormalite;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\FormalitePiece;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormaliteController extends Controller
{
    public function index(Request $request)
    {
        $query = Formalite::with(['dossier.typeActe', 'pieces'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->whereHas('dossier', fn ($d) =>
                    $d->where('reference', 'like', "%{$s}%")
                      ->orWhere('objet', 'like', "%{$s}%"))
                   ->orWhere('organisme', 'like', "%{$s}%")))
            ->when($request->statut,      fn ($q, $s) => $q->where('statut', $s))
            ->when(!$request->statut,     fn ($q)     => $q->where('statut', '!=', 'cloture'))
            ->when($request->urgentes === '1', fn ($q) => $q->urgentes())
            ->when($request->sort === 'montant',   fn ($q) => $q->orderByDesc('montant_calcule'))
            ->when($request->sort === 'organisme', fn ($q) => $q->orderBy('organisme'))
            ->when(!in_array($request->sort, ['montant', 'organisme']), fn ($q) =>
                $q->orderByRaw('echeance_at IS NULL, echeance_at ASC'));

        $stats = [
            'total'       => Formalite::where('statut', '!=', 'cloture')->count(),
            'aDeposer'    => Formalite::where('statut', 'a_deposer')->count(),
            'enCours'     => Formalite::whereIn('statut', ['depose', 'en_attente'])->count(),
            'retourRecu'  => Formalite::where('statut', 'retour_recu')->count(),
            'urgentes'    => Formalite::urgentes()->count(),
            'montantTotal' => (float) Formalite::where('statut', '!=', 'cloture')->sum('montant_calcule'),
        ];

        $formalites = $query->paginate(20)->withQueryString();

        return Inertia::render('Formalites/Index', [
            'formalites' => $formalites->through(fn ($f) => [
                'id'             => $f->id,
                'organisme'      => $f->organisme,
                'organismeLabel' => $f->labelOrganisme(),
                'statut'         => $f->statut?->value,
                'montant_base'   => (float) ($f->montant_base ?? 0),
                'montant_calcule' => (float) ($f->montant_calcule ?? 0),
                'echeance_at'    => $f->echeance_at?->toDateTimeString(),
                'depose_at'      => $f->depose_at?->format('d/m/Y'),
                'retour_at'      => $f->retour_at?->format('d/m/Y'),
                'estUrgente'     => $f->estUrgente(),
                'estDepassee'    => $f->estDepassee(),
                'heuresRestantes' => $f->heuresRestantes(),
                'dossier'        => [
                    'reference' => $f->dossier?->reference,
                    'objet'     => $f->dossier?->objet,
                    'typeActe'  => $f->dossier?->typeActe?->label,
                ],
                'pieces' => $f->pieces->map(fn ($p) => [
                    'id'         => $p->id,
                    'label'      => $p->label,
                    'est_fourni' => (bool) $p->est_fourni,
                ]),
            ]),
            'stats'   => $stats,
            'statuts' => collect(StatutFormalite::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => match($s) {
                    StatutFormalite::ADeposer    => 'À déposer',
                    StatutFormalite::Depose      => 'Déposé',
                    StatutFormalite::EnAttente   => 'En attente retour',
                    StatutFormalite::RetourRecu  => 'Retour reçu',
                    StatutFormalite::Cloture     => 'Clôturé',
                },
            ]),
            'filters' => [
                'q'        => $request->q        ?? '',
                'statut'   => $request->statut   ?? '',
                'urgentes' => $request->urgentes ?? '',
                'sort'     => $request->sort     ?? '',
            ],
        ]);
    }

    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('gererFormalites', $dossier);

        $data = $request->validate([
            'organisme'    => ['required', 'string', 'max:100'],
            'statut'       => ['required', 'string'],
            'montant_base' => ['nullable', 'numeric', 'min:0'],
            'taux'         => ['nullable', 'numeric', 'min:0', 'max:1'],
            'echeance_at'  => ['nullable', 'date'],
            'type_impot'   => ['nullable', 'string', 'max:100'],
            'pieces'       => ['nullable', 'array'],
            'pieces.*.label' => ['required_with:pieces', 'string', 'max:200'],
        ]);

        $formalite = Formalite::create(array_merge(
            ['dossier_id' => $dossier->id],
            collect($data)->except('pieces')->toArray()
        ));

        if ($formalite->taux && $formalite->montant_base) {
            $formalite->calculerMontant();
        }

        foreach ($data['pieces'] ?? [] as $piece) {
            FormalitePiece::create(['formalite_id' => $formalite->id, 'label' => $piece['label'], 'est_fourni' => false]);
        }

        JournalActivite::enregistrer($dossier, "Formalité ajoutée : {$formalite->labelOrganisme()}", 'formalite');

        return back()->with('success', 'Formalité créée.');
    }

    public function update(Request $request, Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        $data = $request->validate([
            'statut'     => ['sometimes', 'string'],
            'depose_at'  => ['sometimes', 'nullable', 'date'],
            'retour_at'  => ['sometimes', 'nullable', 'date'],
            'pieces'     => ['sometimes', 'array'],
            'pieces.*.id'         => ['required_with:pieces', 'integer'],
            'pieces.*.est_fourni' => ['required_with:pieces', 'boolean'],
        ]);

        if (isset($data['pieces'])) {
            foreach ($data['pieces'] as $pieceData) {
                FormalitePiece::where('id', $pieceData['id'])
                    ->where('formalite_id', $formalite->id)
                    ->update([
                        'est_fourni' => $pieceData['est_fourni'],
                        'fourni_at'  => $pieceData['est_fourni'] ? now() : null,
                    ]);
            }
            unset($data['pieces']);
        }

        $formalite->update($data);

        JournalActivite::enregistrer(
            $formalite->dossier,
            "Formalité mise à jour : {$formalite->labelOrganisme()} → statut {$formalite->statut?->value}",
            'formalite'
        );

        return back()->with('success', 'Formalité mise à jour.');
    }

    public function destroy(Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        abort_if(
            $formalite->statut?->value === 'cloture',
            403,
            'Une formalité clôturée ne peut pas être supprimée.'
        );

        $label   = $formalite->labelOrganisme();
        $dossier = $formalite->dossier;

        $formalite->pieces()->delete();
        $formalite->delete();

        JournalActivite::enregistrer($dossier, "Formalité supprimée : {$label}", 'formalite');

        return back()->with('success', "Formalité {$label} supprimée.");
    }
}

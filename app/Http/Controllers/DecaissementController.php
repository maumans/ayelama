<?php

namespace App\Http\Controllers;

use App\Enums\MoyenPaiement;
use App\Models\Decaissement;
use App\Models\Dossier;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Les versements de l'étude aux organismes, saisis depuis l'onglet Facturation du dossier
 * (comptabilité des dossiers, 2026-11-23).
 *
 * Le versement fait au **dépôt** d'une formalité se crée tout seul (`FormaliteController::deposer`) ;
 * celui-ci couvre le reste : un second paiement, un journal d'annonces réglé à part, une correction
 * du moyen ou de la date d'un versement repris.
 *
 * Même droit que la facturation (`gererFacturation`) : comptable, notaire, administrateur, et plus
 * rien une fois le dossier clôturé.
 */
class DecaissementController extends Controller
{
    /** @return array<string, mixed> */
    private function regles(Dossier $dossier): array
    {
        return [
            'libelle'           => ['required', 'string', 'max:255'],
            'organisme'         => ['nullable', 'string', 'max:60'],
            'date_decaissement' => ['required', 'date'],
            'montant'           => ['required', 'numeric', 'min:0.01'],
            'moyen'             => ['nullable', Rule::enum(MoyenPaiement::class)],
            'reference'         => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            // Une formalité **de ce dossier** — jamais celle d'un autre.
            'formalite_id'      => ['nullable', 'integer', Rule::exists('formalites', 'id')->where('dossier_id', $dossier->id)],
        ];
    }

    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('gererFacturation', $dossier);

        $data = $request->validate($this->regles($dossier));

        $d = Decaissement::create([...$data, 'dossier_id' => $dossier->id, 'enregistre_par_id' => $request->user()->id]);

        JournalActivite::enregistrer($dossier, $this->resume('Versement enregistré', $d), 'facturation', ['decaissement_id' => $d->id]);

        return back()->with('success', 'Versement enregistré.');
    }

    public function update(Request $request, Decaissement $decaissement)
    {
        $dossier = $decaissement->dossier;
        $this->authorize('gererFacturation', $dossier);

        $avant = $this->resume('avant', $decaissement);
        // Corrigé à la main, un versement repris n'est plus une reprise : sa saisie est vérifiée.
        $decaissement->update([...$request->validate($this->regles($dossier)), 'reprise' => false]);

        JournalActivite::enregistrer($dossier, $this->resume('Versement corrigé', $decaissement) . " ({$avant})", 'facturation', ['decaissement_id' => $decaissement->id]);

        return back()->with('success', 'Versement corrigé.');
    }

    public function destroy(Decaissement $decaissement)
    {
        $dossier = $decaissement->dossier;
        $this->authorize('gererFacturation', $dossier);

        $resume = $this->resume('Versement supprimé', $decaissement);
        $decaissement->delete();

        JournalActivite::enregistrer($dossier, $resume, 'facturation');

        return back()->with('success', 'Versement supprimé.');
    }

    private function resume(string $quoi, Decaissement $d): string
    {
        return sprintf(
            '%s : %s — %s GNF le %s%s',
            $quoi,
            $d->libelle,
            \App\Support\FormatNombre::milliers($d->montant),
            $d->date_decaissement?->format('d/m/Y'),
            $d->moyen ? ' (' . $d->moyen->label() . ')' : '',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SouhaitAnniversaire;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Les anniversaires des clients, et la trace de ceux qu'on a déjà souhaités.
 *
 * ⚠️ **Le filtrage se fait en PHP, pas en SQL, et c'est délibéré.** La version précédente
 * employait `DATE_FORMAT(date_naissance, '%m-%d')`, propre à **MySQL** : les consignes du dépôt
 * imposent qu'une requête vaille aussi sous SQLite, utilisé par les tests. C'était le seul
 * endroit de l'application à employer cette fonction, et la conséquence se voyait — **aucun test
 * ne couvrait cet écran, et aucun ne le pouvait**.
 *
 * Le coût est mesuré, pas supposé : la comparaison porte sur les seuls clients **physiques ayant
 * une date de naissance**, et une étude notariale en compte des centaines, pas des millions
 * (6 clients au moment d'écrire, dont 4 datés). Le jour où le répertoire change d'ordre de
 * grandeur, la bonne réponse sera une colonne calculée indexée — pas un `whereRaw` qui exclut la
 * moitié des environnements.
 */
class AnniversaireController extends Controller
{
    /** Fenêtres proposées, en jours. Le défaut reprend celle que le code figeait. */
    private const FENETRES = [7, 30, 90];

    private const FENETRE_DEFAUT = 30;

    public function index(Request $request)
    {
        $fenetre = (int) $request->integer('fenetre', self::FENETRE_DEFAUT);
        if (! in_array($fenetre, self::FENETRES, true)) {
            $fenetre = self::FENETRE_DEFAUT;
        }

        // `mois` prime sur `fenetre` : « tout octobre » est une autre question que « les 30
        // prochains jours », et les combiner ne voudrait rien dire.
        $mois = $request->filled('mois')
            ? max(1, min(12, (int) $request->integer('mois')))
            : null;

        $statutDemande = (string) $request->string('statut');
        $statut = in_array($statutDemande, ['souhaites', 'restants'], true) ? $statutDemande : null;

        $recherche = trim((string) $request->string('q'));

        $aujourdhui = CarbonImmutable::today();

        $clients = Client::query()
            ->where('type', 'physique')
            ->whereNotNull('date_naissance')
            ->get();

        $souhaits = SouhaitAnniversaire::query()
            ->whereIn('client_id', $clients->pluck('id'))
            ->with('auteur:id,name')
            ->get()
            ->keyBy(fn (SouhaitAnniversaire $s) => $s->client_id . ':' . $s->annee);

        $lignes = $clients
            ->map(fn (Client $client) => $this->ligne($client, $aujourdhui, $mois, $souhaits))
            ->filter()
            // En mois civil, tout le mois compte, passé compris. En fenêtre glissante, seules
            // les occurrences à venir et dans la fenêtre.
            ->filter(fn (array $l) => $mois !== null
                || ($l['jours_restants'] >= 0 && $l['jours_restants'] <= $fenetre))
            ->when($statut === 'souhaites', fn (Collection $c) => $c->filter(fn (array $l) => $l['souhait'] !== null))
            ->when($statut === 'restants', fn (Collection $c) => $c->filter(fn (array $l) => $l['souhait'] === null))
            ->when($recherche !== '', fn (Collection $c) => $c->filter(
                fn (array $l) => mb_stripos($l['nom'], $recherche) !== false
            ))
            ->sortBy('jours_restants')
            ->values();

        return Inertia::render('Anniversaires/Index', [
            'clients' => $lignes,
            'filtres' => [
                'fenetre' => $fenetre,
                'mois'    => $mois,
                'statut'  => $statut,
                'q'       => $recherche,
            ],
            'fenetresDisponibles' => self::FENETRES,
            // Indicatif déclaré **à un seul endroit** : il compose les liens WhatsApp, et un
            // numéro guinéen se saisit en national (6XX XX XX XX). Donnée `a_verifier` le jour
            // où l'étude suivra des clients hors Guinée.
            'indicatifPaysDefaut' => '224',
        ]);
    }

    /**
     * Une ligne de la liste, ou `null` si le client n'a pas d'occurrence dans le cadrage demandé.
     *
     * ⚠️ **Deux cadrages, deux occurrences.** En fenêtre glissante, l'occurrence est le
     * **prochain** anniversaire — celui qu'on s'apprête à souhaiter. En mois civil, c'est celui
     * **de ce mois-ci, cette année**, même déjà passé : demander « tout octobre » le 25 doit
     * inclure le 20. D'où un `jours_restants` qui peut être négatif, ce que la vue énonce.
     *
     * La distinction dépasse l'affichage : **c'est l'année de l'occurrence qui identifie le
     * souhait**. Se tromper d'année ferait réapparaître un client déjà souhaité, ou masquerait
     * un client qui ne l'a pas été.
     *
     * @param  Collection<string, SouhaitAnniversaire>  $souhaits
     */
    private function ligne(Client $client, CarbonImmutable $aujourdhui, ?int $mois, Collection $souhaits): ?array
    {
        $naissance = CarbonImmutable::parse($client->date_naissance);

        if ($mois !== null) {
            if ((int) $naissance->month !== $mois) {
                return null;
            }
            $occurrence = $this->occurrenceDansAnnee($naissance, (int) $aujourdhui->year);
        } else {
            $occurrence = $this->occurrenceDansAnnee($naissance, (int) $aujourdhui->year);

            if ($occurrence->lt($aujourdhui)) {
                $occurrence = $this->occurrenceDansAnnee($naissance, (int) $aujourdhui->year + 1);
            }
        }

        $souhait = $souhaits->get($client->id . ':' . $occurrence->year);

        return [
            'id'                    => $client->id,
            'nom'                   => $client->prenom_nom
                ?: trim(($client->nom_famille ?? '') . ' ' . ($client->prenoms ?? '')),
            'email'                 => $client->email,
            'telephone'             => $client->telephone,
            'date_naissance'        => $naissance->format('Y-m-d'),
            'prochain_anniversaire' => $occurrence->format('Y-m-d'),
            'annee_occurrence'      => (int) $occurrence->year,
            'jours_restants'        => (int) $aujourdhui->diffInDays($occurrence, false),
            'age_a_venir'           => (int) $occurrence->year - (int) $naissance->year,
            'souhait'               => $souhait ? [
                'souhaite_at' => $souhait->souhaite_at?->format('Y-m-d'),
                'canal'       => $souhait->canalLabel(),
                'auteur'      => $souhait->auteur?->name,
            ] : null,
        ];
    }

    /**
     * L'anniversaire d'une date de naissance dans une année donnée.
     *
     * ⚠️ Un 29 février n'existe pas toutes les années, et `createFromDate(2026, 2, 29)` glisse
     * au 1er mars **en silence**. On le ramène explicitement au 28 : l'étude souhaite ce
     * jour-là, et un décalage muet d'un jour vaut moins qu'une convention énoncée.
     */
    private function occurrenceDansAnnee(CarbonImmutable $naissance, int $annee): CarbonImmutable
    {
        $moisNaissance = (int) $naissance->month;
        $jour = (int) $naissance->day;

        if ($moisNaissance === 2 && $jour === 29 && ! Carbon::createFromDate($annee, 1, 1)->isLeapYear()) {
            $jour = 28;
        }

        return CarbonImmutable::createFromDate($annee, $moisNaissance, $jour)->startOfDay();
    }

    /**
     * Marque un anniversaire comme souhaité, ou retire la marque si elle existe déjà.
     *
     * Une bascule et non deux routes : le bouton de la liste a deux états, et un second point
     * d'entrée aurait demandé à l'écran de savoir lequel appeler — donc de connaître l'état
     * deux fois.
     */
    public function basculer(Request $request, Client $client)
    {
        $valide = $request->validate([
            'annee' => ['required', 'integer', 'min:1900', 'max:2200'],
            'canal' => ['nullable', 'string', 'max:30'],
        ]);

        $existant = SouhaitAnniversaire::where('client_id', $client->id)
            ->where('annee', $valide['annee'])
            ->first();

        if ($existant) {
            $existant->delete();

            return back()->with('success', 'Marque retirée.');
        }

        SouhaitAnniversaire::create([
            'client_id'   => $client->id,
            'annee'       => $valide['annee'],
            'souhaite_at' => now(),
            'user_id'     => $request->user()?->id,
            'canal'       => $valide['canal'] ?? null,
        ]);

        return back()->with('success', 'Anniversaire marqué comme souhaité.');
    }
}

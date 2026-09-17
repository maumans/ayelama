<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AnniversaireController extends Controller
{
    public function index(Request $request)
    {
        // On récupère tous les clients qui ont une date de naissance.
        // Comme MySQL ne gère pas nativement un index sur 'jour-mois' facilement sans colonne calculée,
        // et que le volume de clients n'est probablement pas des millions, on peut 
        // interroger les clients avec une date de naissance et filtrer ou trier.
        // Mieux : on utilise DATE_FORMAT pour extraire mois et jour et filtrer ceux 
        // dont l'anniversaire est dans les 30 prochains jours (ou 15 jours).

        // Pour simplifier et être robuste sur l'année bissextile etc, on va récupérer
        // les clients et calculer en PHP, ou utiliser la clause SQL suivante :
        // on cherche les clients dont (DATE_FORMAT(date_naissance, '%m-%d') >= aujourd'hui) 
        // et (DATE_FORMAT(date_naissance, '%m-%d') <= aujourd'hui + 30 jours).
        // Attention au passage à la nouvelle année (décembre -> janvier).

        $now = Carbon::now();
        $todayStr = $now->format('m-d');
        $futureStr = $now->copy()->addDays(30)->format('m-d');
        
        $query = Client::whereNotNull('date_naissance')
            // Les personnes physiques uniquement ont des anniversaires.
            ->where('type', 'physique');
            
        if ($todayStr <= $futureStr) {
            // Pas de chevauchement d'année
            $query->whereRaw("DATE_FORMAT(date_naissance, '%m-%d') BETWEEN ? AND ?", [$todayStr, $futureStr]);
        } else {
            // Chevauchement d'année (ex: 15 décembre au 14 janvier)
            $query->where(function ($q) use ($todayStr, $futureStr) {
                $q->whereRaw("DATE_FORMAT(date_naissance, '%m-%d') >= ?", [$todayStr])
                  ->orWhereRaw("DATE_FORMAT(date_naissance, '%m-%d') <= ?", [$futureStr]);
            });
        }

        $clients = $query->get()->map(function ($client) use ($now) {
            // Calculer la date du prochain anniversaire
            $dateNaissance = Carbon::parse($client->date_naissance);
            $anniversaireCetteAnnee = $dateNaissance->copy()->year($now->year);
            
            if ($anniversaireCetteAnnee->isPast() && !$anniversaireCetteAnnee->isToday()) {
                $prochainAnniversaire = $anniversaireCetteAnnee->addYear();
            } else {
                $prochainAnniversaire = $anniversaireCetteAnnee;
            }

            $joursRestants = $now->startOfDay()->diffInDays($prochainAnniversaire->startOfDay(), false);
            $ageBientot = $prochainAnniversaire->year - $dateNaissance->year;

            return [
                'id' => $client->id,
                'nom' => $client->prenom_nom ?: ($client->nom_famille . ' ' . $client->prenoms),
                'email' => $client->email,
                'telephone' => $client->telephone,
                'date_naissance' => $client->date_naissance->format('Y-m-d'),
                'prochain_anniversaire' => $prochainAnniversaire->format('Y-m-d'),
                'jours_restants' => (int) $joursRestants,
                'age_a_venir' => $ageBientot,
            ];
        })->sortBy('jours_restants')->values();

        return Inertia::render('Anniversaires/Index', [
            'clients' => $clients,
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace des souhaits d'anniversaire déjà adressés à un client.
 *
 * L'écran « Anniversaires » listait les clients à souhaiter sans jamais retenir ceux qui
 * l'avaient été : impossible de savoir, en reprenant la liste le lendemain, qui restait à
 * appeler. Deux personnes de l'étude pouvaient souhaiter au même client, et personne ne
 * pouvait dire qu'un client avait été oublié.
 *
 * **Une ligne par client et par occurrence**, l'occurrence étant l'année de l'anniversaire
 * fêté — pas celle du geste. Les deux diffèrent : souhaiter le 31 décembre pour un
 * anniversaire du 2 janvier pose le souhait sur l'année suivante, et c'est bien celle-là
 * qu'il faut cocher. La contrainte d'unicité porte donc sur `(client_id, annee)`.
 *
 * ⚠️ `canal` est **déclaratif** : l'application n'envoie rien (décision de l'étude — un
 * souhait d'anniversaire envoyé par une machine se voit). Elle enregistre ce que la personne
 * déclare avoir fait, pour que l'information serve à la suivante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('souhaits_anniversaire', function (Blueprint $table) {
            $table->id();

            // Le client disparaît, sa trace de souhait n'a plus d'objet.
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Année de l'anniversaire fêté — un entier, pas une date : c'est une occurrence.
            $table->smallInteger('annee');

            $table->timestamp('souhaite_at');

            // Qui a souhaité — nullOnDelete : le départ d'un collaborateur n'efface pas le fait.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // email · telephone · whatsapp · de_vive_voix · autre — libre plutôt qu'énuméré :
            // la liste n'est pas arrêtée et une contrainte inventée est pire qu'une absente.
            $table->string('canal', 30)->nullable();
            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->unique(['client_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('souhaits_anniversaire');
    }
};

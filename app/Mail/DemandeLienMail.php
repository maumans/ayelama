<?php

namespace App\Mail;

use App\Models\Demande;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeLienMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Demande $demande) {}

    public function build(): self
    {
        $officeNom = Setting::get('office_nom', config('app.name'));

        return $this
            ->subject("{$officeNom} — Complétez votre dossier en ligne")
            ->view('emails.demande-lien', [
                'officeNom' => $officeNom,
                'lien'      => route('intake.show', $this->demande->token),
                'expireAt'  => $this->demande->expire_at->format('d/m/Y'),
                'typeActe'  => $this->demande->typeActe?->label,
            ]);
    }
}

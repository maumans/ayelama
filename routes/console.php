<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ayelema:alerter-echeances')->hourly();
Schedule::command('ayelema:alerter-formalites')->hourly();

// Quotidienne et non horaire : une échéance de liquidation se compte en années, pas en heures.
// Voir App\Enums\JalonLiquidation — ces délais ne sont pas garantis et n'ont aucun effet bloquant.
Schedule::command('ayelema:alerter-liquidations')->dailyAt('07:00');

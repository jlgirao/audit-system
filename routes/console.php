<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincroniza evidências do Dropbox a cada 15 minutos, conforme desenhado
// no pipeline (fila "sync"). Requer `php artisan schedule:run` no cron
// do servidor (ou `php artisan schedule:work` em desenvolvimento) e
// `php artisan queue:work` rodando para processar os jobs enfileirados.
Schedule::command('app:sincronizar-dropbox')->everyFifteenMinutes()->withoutOverlapping();

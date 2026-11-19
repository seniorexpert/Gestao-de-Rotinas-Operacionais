<?php

use App\Services\ExecucoesDiarias;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rotinas:gerar-execucoes', function (ExecucoesDiarias $execucoes): void {
    $total = $execucoes->gerar(now()->toImmutable());

    $this->info("$total execução(ões) gerada(s) para o dia.");
})->purpose('Gerar as execuções diárias das rotinas ativas');

Schedule::command('rotinas:gerar-execucoes')
    ->daily()
    ->timezone(ExecucoesDiarias::FUSO)
    ->withoutOverlapping(10);

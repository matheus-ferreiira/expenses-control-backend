<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Janela rolante de recorrências — também disparada lazy no TransactionService (1x/dia por usuário),
// então este agendamento é redundância barata para quando houver cron configurado.
Schedule::command('finance:extend-recurrences --prune')->dailyAt('03:00');

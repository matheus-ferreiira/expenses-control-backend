<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recorrências são mantidas pelo scheduler; consultas financeiras não escrevem dados.
Schedule::command('finance:extend-recurrences --prune')->dailyAt('03:00');
// Compras no cartão viram gasto na data (03:05 UTC = 00:05 em Brasília); dias perdidos são recuperados.
Schedule::command('finance:realize-card-charges')->dailyAt('03:05');

// Rotinas de tarefas — idem: lazy no TaskService::list + redundância agendada.
Schedule::command('tasks:roll-recurrences')->dailyAt('03:10');

// Bancos conectados (Pluggy): o MeuPluggy lê cada banco 1x por dia, num horário que muda. De hora em hora
// perguntamos à Pluggy se ela leu de novo (uma chamada) e só então relemos contas e transações.
Schedule::command('bank-sync:pull')->hourly()->withoutOverlapping();

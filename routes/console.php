<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recorrências são mantidas pelo scheduler; consultas financeiras não escrevem dados.
Schedule::command('finance:extend-recurrences --prune')->dailyAt('03:00');

// Rotinas de tarefas — idem: lazy no TaskService::list + redundância agendada.
Schedule::command('tasks:roll-recurrences')->dailyAt('03:10');

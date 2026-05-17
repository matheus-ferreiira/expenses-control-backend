<?php

namespace Database\Seeders;

use App\Domains\Calendar\Enums\EventSource;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Finance\Enums\AccountType;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Enums\GoalType;
use App\Domains\Goals\Models\Goal;
use App\Domains\Habits\Enums\FrequencyType;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;
use App\Domains\Notes\Models\Note;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->command->info("Seeding data for: {$user->email}");
            $this->seedUser($user);
        }

        $this->command->info('Demo data seeded successfully!');
    }

    private function seedUser(User $user): void
    {
        $this->seedHabits($user);
        $this->seedTasks($user);
        $this->seedFinance($user);
        $this->seedGoals($user);
        $this->seedCalendar($user);
        $this->seedNotes($user);
    }

    // ── Hábitos ──────────────────────────────────────────────────────────────

    private function seedHabits(User $user): void
    {
        if (Habit::where('user_id', $user->id)->count() >= 8) {
            return;
        }

        $habits = [
            ['name' => 'Meditar 10 minutos',      'category' => 'Mente',       'frequency' => FrequencyType::Daily,  'color' => '#818cf8', 'rate' => 0.85],
            ['name' => 'Beber 2L de água',         'category' => 'Saúde',       'frequency' => FrequencyType::Daily,  'color' => '#38bdf8', 'rate' => 0.75],
            ['name' => 'Exercitar 30 minutos',     'category' => 'Saúde',       'frequency' => FrequencyType::Daily,  'color' => '#f87171', 'rate' => 0.60],
            ['name' => 'Ler 20 páginas',           'category' => 'Aprendizado', 'frequency' => FrequencyType::Daily,  'color' => '#a78bfa', 'rate' => 0.70],
            ['name' => 'Revisar flashcards',       'category' => 'Aprendizado', 'frequency' => FrequencyType::Daily,  'color' => '#c084fc', 'rate' => 0.55],
            ['name' => 'Sem redes sociais manhã',  'category' => 'Foco',        'frequency' => FrequencyType::Daily,  'color' => '#fb923c', 'rate' => 0.65],
            ['name' => 'Registrar gastos',         'category' => 'Finanças',    'frequency' => FrequencyType::Daily,  'color' => '#4ade80', 'rate' => 0.80],
            ['name' => 'Alongamento',              'category' => 'Saúde',       'frequency' => FrequencyType::Daily,  'color' => '#f472b6', 'rate' => 0.50],
            ['name' => 'Revisão semanal',          'category' => 'Foco',        'frequency' => FrequencyType::Weekly, 'color' => '#facc15', 'rate' => 0.90],
            ['name' => 'Ligar para a família',     'category' => 'Mente',       'frequency' => FrequencyType::Weekly, 'color' => '#34d399', 'rate' => 0.85],
        ];

        foreach ($habits as $data) {
            $habit = Habit::create([
                'user_id'          => $user->id,
                'name'             => $data['name'],
                'category'         => $data['category'],
                'frequency_type'   => $data['frequency'],
                'target_frequency' => 1,
                'color'            => $data['color'],
                'start_date'       => now()->subDays(60),
            ]);

            // Generate logs for past 45 days based on completion rate
            $completionRate = $data['rate'];
            $daysToCheck = $data['frequency'] === FrequencyType::Weekly ? 45 : 45;

            for ($i = $daysToCheck; $i >= 1; $i--) {
                $date = now()->subDays($i)->toDateString();

                // For weekly habits, only check every 7 days
                if ($data['frequency'] === FrequencyType::Weekly && $i % 7 !== 0) {
                    continue;
                }

                if (rand(1, 100) <= $completionRate * 100) {
                    HabitLog::firstOrCreate([
                        'habit_id'       => $habit->id,
                        'completed_date' => $date,
                    ]);
                }
            }
        }
    }

    // ── Tarefas ───────────────────────────────────────────────────────────────

    private function seedTasks(User $user): void
    {
        if (Task::where('user_id', $user->id)->count() >= 15) {
            return;
        }

        $tasks = [
            // Urgente
            ['title' => 'Revisar relatório trimestral',       'priority' => TaskPriority::Urgent,  'status' => TaskStatus::InProgress, 'due' => now()->addDays(1)],
            ['title' => 'Responder e-mails pendentes',        'priority' => TaskPriority::High,    'status' => TaskStatus::Pending,    'due' => now()->subDays(1)],
            // High priority
            ['title' => 'Reunião com cliente — preparar deck', 'priority' => TaskPriority::High,   'status' => TaskStatus::Pending,    'due' => now()->addDays(3)],
            ['title' => 'Renovar seguro do carro',            'priority' => TaskPriority::High,    'status' => TaskStatus::Pending,    'due' => now()->addDays(7)],
            ['title' => 'Atualizar currículo',                'priority' => TaskPriority::High,    'status' => TaskStatus::InProgress, 'due' => now()->addDays(14)],
            // Normal priority
            ['title' => 'Organizar arquivos do computador',   'priority' => TaskPriority::Normal,  'status' => TaskStatus::Pending,    'due' => now()->addDays(5)],
            ['title' => 'Pesquisar curso de investimentos',   'priority' => TaskPriority::Normal,  'status' => TaskStatus::Pending,    'due' => now()->addDays(10)],
            ['title' => 'Marcar consulta médica',             'priority' => TaskPriority::Normal,  'status' => TaskStatus::Pending,    'due' => now()->addDays(20)],
            ['title' => 'Limpar e-mails antigos',             'priority' => TaskPriority::Normal,  'status' => TaskStatus::InProgress, 'due' => null],
            ['title' => 'Configurar backup automático',       'priority' => TaskPriority::Normal,  'status' => TaskStatus::Pending,    'due' => now()->addDays(30)],
            // Low priority
            ['title' => 'Reorganizar estante de livros',      'priority' => TaskPriority::Low,     'status' => TaskStatus::Pending,    'due' => null],
            ['title' => 'Pesquisar destinos para férias',     'priority' => TaskPriority::Low,     'status' => TaskStatus::Pending,    'due' => null],
            // Completed
            ['title' => 'Pagar fatura do cartão',             'priority' => TaskPriority::Urgent,  'status' => TaskStatus::Completed,  'due' => now()->subDays(5)],
            ['title' => 'Enviar declaração do IR',            'priority' => TaskPriority::High,    'status' => TaskStatus::Completed,  'due' => now()->subDays(10)],
            ['title' => 'Instalar novo software de gestão',   'priority' => TaskPriority::Normal,  'status' => TaskStatus::Completed,  'due' => now()->subDays(3)],
            ['title' => 'Preparar apresentação do projeto',   'priority' => TaskPriority::High,    'status' => TaskStatus::Completed,  'due' => now()->subDays(7)],
            ['title' => 'Contratar serviço de streaming',     'priority' => TaskPriority::Low,     'status' => TaskStatus::Completed,  'due' => null],
        ];

        foreach ($tasks as $i => $data) {
            Task::create([
                'user_id'      => $user->id,
                'title'        => $data['title'],
                'priority'     => $data['priority'],
                'status'       => $data['status'],
                'due_date'     => $data['due'],
                'position'     => ($i + 1) * 10,
                'completed_at' => $data['status'] === TaskStatus::Completed ? now()->subDays(rand(1, 14)) : null,
            ]);
        }
    }

    // ── Finanças ──────────────────────────────────────────────────────────────

    private function seedFinance(User $user): void
    {
        if (BankAccount::where('user_id', $user->id)->count() >= 2) {
            return;
        }

        // Conta corrente
        $checking = BankAccount::create([
            'user_id'   => $user->id,
            'name'      => 'Conta Corrente',
            'bank_name' => 'Nubank',
            'type'      => AccountType::Checking,
            'balance'   => 4850.00,
            'currency'  => 'BRL',
            'color'     => '#818cf8',
            'is_active' => true,
        ]);

        // Poupança
        $savings = BankAccount::create([
            'user_id'   => $user->id,
            'name'      => 'Poupança',
            'bank_name' => 'Caixa Econômica',
            'type'      => AccountType::Savings,
            'balance'   => 18300.00,
            'currency'  => 'BRL',
            'color'     => '#34d399',
            'is_active' => true,
        ]);

        // Cartão de crédito
        CreditCard::create([
            'user_id'         => $user->id,
            'bank_account_id' => $checking->id,
            'name'            => 'Nubank Mastercard',
            'limit_amount'    => 8000.00,
            'closing_day'     => 20,
            'due_day'         => 27,
            'color'           => '#a855f7',
            'is_active'       => true,
        ]);

        // Transações da conta corrente — últimos 6 meses
        $expenseCategories = [
            ['desc' => 'Supermercado Extra',      'min' => 180, 'max' => 420, 'freq' => 4],
            ['desc' => 'iFood',                   'min' => 35,  'max' => 90,  'freq' => 8],
            ['desc' => 'Uber',                    'min' => 15,  'max' => 50,  'freq' => 6],
            ['desc' => 'Farmácia',                'min' => 20,  'max' => 150, 'freq' => 2],
            ['desc' => 'Academia Smart Fit',      'min' => 89,  'max' => 89,  'freq' => 1],
            ['desc' => 'Netflix',                 'min' => 39,  'max' => 39,  'freq' => 1],
            ['desc' => 'Spotify',                 'min' => 19,  'max' => 19,  'freq' => 1],
            ['desc' => 'Conta de luz',            'min' => 110, 'max' => 280, 'freq' => 1],
            ['desc' => 'Internet Vivo',           'min' => 129, 'max' => 129, 'freq' => 1],
            ['desc' => 'Padaria',                 'min' => 15,  'max' => 45,  'freq' => 5],
            ['desc' => 'Gasolina',                'min' => 100, 'max' => 200, 'freq' => 3],
            ['desc' => 'Restaurante',             'min' => 40,  'max' => 120, 'freq' => 4],
        ];

        $incomeCategories = [
            ['desc' => 'Salário',              'min' => 5500, 'max' => 5500, 'freq' => 1],
            ['desc' => 'Freelance',            'min' => 500,  'max' => 2500, 'freq' => 1],
        ];

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();

            // Salário no dia 5
            Transaction::create([
                'user_id'          => $user->id,
                'account_id'       => $checking->id,
                'type'             => TransactionType::Income,
                'amount'           => 5500.00,
                'description'      => 'Salário',
                'transaction_date' => $monthStart->copy()->addDays(4)->toDateString(),
            ]);

            // Freelance esporádico (70% dos meses)
            if (rand(1, 10) <= 7) {
                Transaction::create([
                    'user_id'          => $user->id,
                    'account_id'       => $checking->id,
                    'type'             => TransactionType::Income,
                    'amount'           => rand(500, 2500),
                    'description'      => 'Freelance',
                    'transaction_date' => $monthStart->copy()->addDays(rand(10, 25))->toDateString(),
                ]);
            }

            // Despesas recorrentes
            foreach ($expenseCategories as $cat) {
                for ($f = 0; $f < $cat['freq']; $f++) {
                    $day = rand(1, 28);
                    Transaction::create([
                        'user_id'          => $user->id,
                        'account_id'       => $checking->id,
                        'type'             => TransactionType::Expense,
                        'amount'           => rand($cat['min'], $cat['max']),
                        'description'      => $cat['desc'],
                        'transaction_date' => $monthStart->copy()->addDays($day - 1)->toDateString(),
                    ]);
                }
            }

            // Transferência para poupança
            if (rand(1, 10) <= 8) {
                $transferAmount = rand(200, 800);
                Transaction::create([
                    'user_id'          => $user->id,
                    'account_id'       => $checking->id,
                    'type'             => TransactionType::Expense,
                    'amount'           => $transferAmount,
                    'description'      => 'Transferência — Poupança',
                    'transaction_date' => $monthStart->copy()->addDays(9)->toDateString(),
                ]);
                Transaction::create([
                    'user_id'          => $user->id,
                    'account_id'       => $savings->id,
                    'type'             => TransactionType::Income,
                    'amount'           => $transferAmount,
                    'description'      => 'Transferência recebida',
                    'transaction_date' => $monthStart->copy()->addDays(9)->toDateString(),
                ]);
            }
        }
    }

    // ── Metas ─────────────────────────────────────────────────────────────────

    private function seedGoals(User $user): void
    {
        if (Goal::where('user_id', $user->id)->count() >= 3) {
            return;
        }

        $goals = [
            [
                'title'          => 'Reserva de emergência',
                'type'           => GoalType::Financial,
                'status'         => GoalStatus::Active,
                'target_amount'  => 30000.00,
                'current_amount' => 8300.00,
                'target_date'    => now()->addMonths(18)->toDateString(),
                'description'    => '6 meses de gastos mensais',
            ],
            [
                'title'          => 'Viagem para a Europa',
                'type'           => GoalType::Personal,
                'status'         => GoalStatus::Active,
                'target_amount'  => 15000.00,
                'current_amount' => 3200.00,
                'target_date'    => now()->addMonths(12)->toDateString(),
                'description'    => 'Portugal, Espanha e França',
            ],
            [
                'title'          => 'Carro novo',
                'type'           => GoalType::Financial,
                'status'         => GoalStatus::Active,
                'target_amount'  => 60000.00,
                'current_amount' => 14500.00,
                'target_date'    => now()->addMonths(36)->toDateString(),
                'description'    => null,
            ],
            [
                'title'          => 'Curso de inglês avançado',
                'type'           => GoalType::Learning,
                'status'         => GoalStatus::Completed,
                'target_amount'  => 2400.00,
                'current_amount' => 2400.00,
                'target_date'    => now()->subMonths(1)->toDateString(),
                'description'    => 'Nivel C1',
            ],
            [
                'title'          => 'Perder 8kg',
                'type'           => GoalType::Health,
                'status'         => GoalStatus::Active,
                'target_amount'  => 8.00,
                'current_amount' => 3.50,
                'target_date'    => now()->addMonths(4)->toDateString(),
                'description'    => 'Meta de saúde para 2026',
            ],
        ];

        foreach ($goals as $data) {
            Goal::create([
                'user_id'        => $user->id,
                'title'          => $data['title'],
                'type'           => $data['type'],
                'status'         => $data['status'],
                'target_amount'  => $data['target_amount'],
                'current_amount' => $data['current_amount'],
                'target_date'    => $data['target_date'],
                'description'    => $data['description'],
                'completed_at'   => $data['status'] === GoalStatus::Completed ? now()->subMonth() : null,
            ]);
        }
    }

    // ── Agenda ────────────────────────────────────────────────────────────────

    private function seedCalendar(User $user): void
    {
        if (CalendarEvent::where('user_id', $user->id)->count() >= 8) {
            return;
        }

        $events = [
            ['title' => 'Reunião de alinhamento',    'days' => 2,   'duration' => 60,  'color' => '#818cf8'],
            ['title' => 'Dentista',                   'days' => 5,   'duration' => 90,  'color' => '#f87171'],
            ['title' => 'Almoço com equipe',          'days' => 3,   'duration' => 90,  'color' => '#34d399'],
            ['title' => 'Review de código',           'days' => 1,   'duration' => 60,  'color' => '#818cf8'],
            ['title' => 'Webinar — Vue 3 Patterns',   'days' => 7,   'duration' => 120, 'color' => '#a78bfa'],
            ['title' => 'Revisão mensal de finanças', 'days' => 10,  'duration' => 60,  'color' => '#4ade80'],
            ['title' => 'Aniversário da empresa',     'days' => 14,  'duration' => 180, 'color' => '#facc15', 'allDay' => true],
            ['title' => 'Sprint planning',            'days' => 0,   'duration' => 90,  'color' => '#818cf8'],
            ['title' => 'Consulta médica check-up',  'days' => 21,  'duration' => 60,  'color' => '#f87171'],
            ['title' => 'Curso online — módulo 4',   'days' => 4,   'duration' => 120, 'color' => '#c084fc'],
            ['title' => 'Happy hour',                 'days' => 6,   'duration' => 180, 'color' => '#fb923c'],
            ['title' => 'Planejamento trimestral',    'days' => -3,  'duration' => 120, 'color' => '#818cf8'],
        ];

        foreach ($events as $data) {
            $start = now()->addDays($data['days'])->setHour(rand(8, 17))->setMinute(0)->setSecond(0);
            $isAllDay = $data['allDay'] ?? false;

            CalendarEvent::create([
                'user_id'     => $user->id,
                'title'       => $data['title'],
                'start_date'  => $isAllDay ? $start->toDateString() : $start,
                'end_date'    => $isAllDay ? $start->toDateString() : $start->copy()->addMinutes($data['duration']),
                'is_all_day'  => $isAllDay,
                'color'       => $data['color'],
                'source'      => EventSource::Manual,
            ]);
        }
    }

    // ── Notas ─────────────────────────────────────────────────────────────────

    private function seedNotes(User $user): void
    {
        if (Note::where('user_id', $user->id)->count() >= 4) {
            return;
        }

        $notes = [
            [
                'title'       => 'Ideias para novos projetos',
                'content'     => "## Projetos em mente\n\n- App de controle de gastos com IA\n- Sistema de agendamento para freelancers\n- Plugin Obsidian para GTD\n\n## Próximos passos\n\nEscolher um projeto e definir MVP até fim do mês.",
                'is_pinned'   => true,
                'is_favorite' => true,
            ],
            [
                'title'       => 'Recursos de estudo',
                'content'     => "## Livros\n- The Pragmatic Programmer\n- Clean Architecture (ainda lendo)\n- Atomic Habits — CONCLUÍDO ✓\n\n## Cursos online\n- Vue Mastery — Vue 3 Composition API\n- Laracasts — Laravel 12 From Scratch",
                'is_pinned'   => true,
                'is_favorite' => false,
            ],
            [
                'title'       => 'Notas da reunião — 12/05',
                'content'     => "**Participantes:** Equipe de produto\n\n**Decisões:**\n- Lançar versão beta em junho\n- Priorizar mobile-first para próximo sprint\n- Revisar estrutura de preços\n\n**Action items:**\n- [ ] Matheus: Criar mockups do onboarding\n- [ ] Time: Review de backlog quinta-feira",
                'is_pinned'   => false,
                'is_favorite' => false,
            ],
            [
                'title'       => 'Receitas saudáveis',
                'content'     => "## Café da manhã\n- Aveia com frutas e mel\n- Omelete de legumes\n\n## Almoço\n- Bowl de quinoa com frango\n- Salada colorida com azeite\n\n## Jantar\n- Sopa de legumes\n- Peixe grelhado com batata doce",
                'is_pinned'   => false,
                'is_favorite' => true,
            ],
            [
                'title'       => 'Senhas e acessos (dev)',
                'content'     => "**ATENÇÃO: Use um gerenciador de senhas real em produção!**\n\nAmbiente de desenvolvimento:\n- Staging: https://staging.vault.app\n- DB local: postgres://localhost/productivity_dev\n- Redis: localhost:6379",
                'is_pinned'   => false,
                'is_favorite' => false,
            ],
            [
                'title'       => 'Lista de compras do mês',
                'content'     => "## Supermercado\n- [ ] Aveia\n- [ ] Azeite extra virgem\n- [x] Detergente\n- [ ] Papel toalha\n- [ ] Frutas da estação\n\n## Farmácia\n- [x] Vitamina D\n- [ ] Filtro solar FPS 70",
                'is_pinned'   => false,
                'is_favorite' => false,
            ],
        ];

        foreach ($notes as $data) {
            Note::create([
                'user_id'    => $user->id,
                'title'      => $data['title'],
                'content'    => $data['content'],
                'is_pinned'  => $data['is_pinned'],
                'is_favorite' => $data['is_favorite'],
            ]);
        }
    }
}

<?php

namespace App\Domains\Assistant\Services;

use App\Domains\Assistant\Models\Conversation;
use App\Domains\Assistant\Models\Message;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Habits\Models\Habit;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AssistantService
{
    public function __construct(
        private readonly OpenAiPlanner $planner,
        private readonly AssistantActions $actions,
    ) {}

    public function context(User $user, string $date): array
    {
        $timezone = $user->timezone ?: 'America/Sao_Paulo';
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $end = $start->addDays(13)->endOfDay();
        $tasks = Task::forUser($user->id)->notArchived()->pending()
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', $end->format('Y-m-d H:i:s')))
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->limit(101)->get();
        $habits = Habit::forUser($user->id)->active()->orderBy('name')->orderBy('id')->limit(101)->get();
        $events = CalendarEvent::forUser($user->id)->inRange($start->utc()->format('Y-m-d H:i:s'), $end->utc()->format('Y-m-d H:i:s'))
            ->orderBy('start_date')->orderBy('id')->limit(101)->get();
        $pack = fn ($items, $entity) => $items->take(100)->map(fn ($model) => [
            'id' => $model->id,
            ...array_map(fn ($value) => is_string($value) ? Str::limit($value, 350) : $value, $this->actions->snapshot($model, $entity, $timezone)),
        ])->all();

        return [
            'now' => now($timezone)->toIso8601String(), 'timezone' => $timezone,
            'selected_date' => $date, 'window_end' => $end->toDateString(),
            'limits' => 'Até 100 itens por módulo. Tarefas pendentes sem data e até o fim da janela (inclui atrasadas); hábitos ativos; eventos dos próximos 14 dias a partir da data selecionada.',
            'truncated' => ['tasks' => $tasks->count() > 100, 'habits' => $habits->count() > 100, 'events' => $events->count() > 100],
            'tasks' => $pack($tasks, 'task'), 'habits' => $pack($habits, 'habit'), 'events' => $pack($events, 'event'),
        ];
    }

    public function send(User $user, Conversation $conversation, array $input): Message
    {
        return $this->exclusive($user, function () use ($user, $conversation, $input) {
            $existing = $conversation->messages()->where('request_id', $input['request_id'])->where('role', 'assistant')->first();
            if ($existing) {
                $original = $conversation->messages()->where('request_id', $input['request_id'])->where('role', 'user')->first();
                abort_unless($original?->content === $input['message'], 409, 'Esta mensagem já foi enviada com outro conteúdo.');

                return $existing;
            }
            abort_unless(config('assistant.api_key'), 503, 'O assistente ainda não foi configurado no servidor.');
            abort_if($conversation->messages()->count() >= 200, 422, 'Esta conversa chegou ao limite. Comece uma nova conversa.');
            $key = 'assistant:daily:'.$user->id.':'.now($user->timezone ?: 'America/Sao_Paulo')->toDateString();
            abort_if(RateLimiter::tooManyAttempts($key, max(1, config('assistant.daily_requests'))), 429,
                'Você atingiu o limite diário do assistente. Tente novamente amanhã.');
            RateLimiter::hit($key, 86400);

            $history = $conversation->messages()->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()->reverse()
                ->map(fn (Message $message) => [
                    'role' => $message->role,
                    'content' => $message->role === 'user' ? $message->content : json_encode([
                        'message' => $message->content, 'status' => $message->status,
                        'actions' => $message->actions, 'results' => $message->results,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ])->values()->all();
            $result = $this->planner->respond($this->context($user, $input['date']), $history, $input['message']);
            $actions = $this->actions->prepare($user, $result['actions']);

            return DB::transaction(function () use ($conversation, $input, $result, $actions) {
                $conversation->messages()->where('status', 'pending')->update(['status' => 'superseded']);
                $conversation->messages()->create([
                    'request_id' => $input['request_id'], 'role' => 'user', 'content' => $input['message'],
                ]);
                $message = $conversation->messages()->create([
                    'request_id' => $input['request_id'], 'role' => 'assistant', 'content' => $result['message'],
                    'actions' => $actions, 'status' => $actions ? 'pending' : null,
                    'expires_at' => $actions ? now()->addDay() : null,
                ]);
                if ($conversation->title === 'Nova conversa') {
                    $conversation->title = Str::limit($input['message'], 100);
                }
                $conversation->touch();

                return $message;
            });
        });
    }

    public function decide(User $user, Conversation $conversation, string $messageId, bool $approve): Message
    {
        return $this->exclusive($user, fn () => DB::transaction(function () use ($user, $conversation, $messageId, $approve) {
            $message = $conversation->messages()->lockForUpdate()->findOrFail($messageId);
            if (($approve && $message->status === 'applied') || (! $approve && $message->status === 'rejected')) {
                return $message;
            }
            abort_unless($message->status === 'pending', 409, 'Esta proposta não está mais disponível. Peça um novo plano.');
            abort_if($approve && $message->expires_at?->isPast(), 409, 'Esta proposta expirou. Peça um plano atualizado.');
            if ($approve) {
                $message->results = $this->actions->apply($user, $message->actions);
            }
            $message->status = $approve ? 'applied' : 'rejected';
            $message->save();
            $conversation->touch();

            return $message;
        }));
    }

    private function exclusive(User $user, callable $callback): mixed
    {
        $lock = Cache::lock('assistant:user:'.$user->id, 180);
        abort_unless($lock->get(), 409, 'Há uma solicitação em andamento. Aguarde e tente novamente.');
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}

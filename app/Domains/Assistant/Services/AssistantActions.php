<?php

namespace App\Domains\Assistant\Services;

use App\Domains\Calendar\DTOs\CalendarEventDTO;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Calendar\Services\CalendarService;
use App\Domains\Habits\DTOs\HabitDTO;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Services\HabitService;
use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Services\TaskService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The only operations an assistant proposal can execute. No arbitrary routes or SQL. */
final class AssistantActions
{
    public const MODELS = ['task' => Task::class, 'habit' => Habit::class, 'event' => CalendarEvent::class];

    public function __construct(
        private readonly TaskService $tasks,
        private readonly HabitService $habits,
        private readonly CalendarService $calendar,
    ) {}

    public function fields(string $entity): array
    {
        return match ($entity) {
            'task' => ['title', 'description', 'priority', 'due_date', 'due_time', 'estimated_minutes', 'recurrence_type'],
            'habit' => ['name', 'description', 'frequency_type', 'target_frequency', 'target_days', 'start_date'],
            'event' => ['title', 'description', 'location', 'start_date', 'end_date', 'is_all_day'],
        };
    }

    public function snapshot(Model $model, string $entity, string $timezone = 'America/Sao_Paulo'): array
    {
        $data = $model->only($this->fields($entity));
        if ($entity === 'task') {
            $data['due_date'] = $model->due_date?->format('Y-m-d');
            $data['due_time'] = $model->has_due_time ? $model->due_date?->format('H:i') : null;
            $data['priority'] = $model->priority->value;
            $data['recurrence_type'] = $model->recurrence_type->value;
        } elseif ($entity === 'habit') {
            $data['frequency_type'] = $model->frequency_type->value;
            $data['start_date'] = $model->start_date?->format('Y-m-d');
        } else {
            $data['start_date'] = $model->start_date->copy()->setTimezone($timezone)->format('Y-m-d\TH:i');
            $data['end_date'] = $model->end_date->copy()->setTimezone($timezone)->format('Y-m-d\TH:i');
        }

        return $data;
    }

    public function fingerprint(Model $model): string
    {
        $attributes = $model->getRawOriginal();
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    public function prepare(User $user, array $actions): array
    {
        Validator::make(['actions' => $actions], [
            'actions' => ['present', 'array', 'list', 'max:30'],
            'actions.*' => ['required', 'array:entity,operation,id,data'],
            'actions.*.entity' => ['required', Rule::in(array_keys(self::MODELS))],
            'actions.*.operation' => ['required', Rule::in(['create', 'update', 'delete'])],
            'actions.*.id' => ['present', 'nullable', 'uuid'],
            'actions.*.data' => ['present', 'array'],
        ])->validate();

        $seen = [];

        return array_map(function (array $action) use ($user, &$seen) {
            ['entity' => $entity, 'operation' => $operation, 'id' => $id, 'data' => $data] = $action;
            $timezone = $user->timezone ?: 'America/Sao_Paulo';
            $before = null;
            $fingerprint = null;
            if ($operation !== 'create') {
                $model = self::MODELS[$entity]::where('user_id', $user->id)->find($id);
                if (! $model || isset($seen[$entity.':'.$id])) {
                    throw ValidationException::withMessages(['actions' => 'O plano contém um registro indisponível ou repetido. Peça um novo plano.']);
                }
                $seen[$entity.':'.$id] = true;
                $before = $this->snapshot($model, $entity, $timezone);
                $fingerprint = $this->fingerprint($model);
            } elseif ($id !== null) {
                throw ValidationException::withMessages(['actions' => 'Uma criação não pode conter um ID existente.']);
            }

            $allowed = $operation === 'delete' ? [] : $this->fields($entity);
            if (array_diff(array_keys($data), $allowed)) {
                throw ValidationException::withMessages(['actions' => 'O plano contém campos não permitidos.']);
            }
            // Null means unchanged/default; empty strings may explicitly clear descriptions.
            $data = array_filter($data, fn ($value) => $value !== null);
            if ($operation !== 'delete') {
                if ($operation === 'update' && ! $data) {
                    throw ValidationException::withMessages(['actions' => 'A alteração precisa informar os novos valores.']);
                }
                $data = $this->validateData($entity, $data, $before);
                if ($operation === 'create' && $entity === 'habit') {
                    $data['start_date'] ??= now($user->timezone ?: 'America/Sao_Paulo')->toDateString();
                }
            }

            return compact('entity', 'operation', 'id', 'data', 'before', 'fingerprint', 'timezone');
        }, $actions);
    }

    private function validateData(string $entity, array $data, ?array $before): array
    {
        $rules = match ($entity) {
            'task' => [
                'title' => ['required', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:6000'],
                'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
                'due_date' => ['nullable', 'date_format:Y-m-d'],
                'due_time' => ['nullable', 'date_format:H:i'],
                'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
                'recurrence_type' => ['nullable', Rule::in(['none', 'daily', 'weekdays', 'weekly', 'monthly', 'yearly'])],
            ],
            'habit' => [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:6000'],
                'frequency_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'custom'])],
                'target_frequency' => ['nullable', 'integer', 'min:1', 'max:365'],
                'target_days' => ['nullable', 'array', 'max:7'],
                'target_days.*' => ['integer', 'between:0,6', 'distinct'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
            ],
            'event' => [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:6000'],
                'location' => ['nullable', 'string', 'max:255'],
                'start_date' => ['required', 'date_format:Y-m-d\TH:i'],
                'end_date' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_date'],
                'is_all_day' => ['nullable', 'boolean'],
            ],
        };
        $merged = array_replace($before ?? [], $data);
        Validator::make($merged, $rules)->validate();
        if ($entity === 'task') {
            if ((! empty($merged['due_time']) || ($merged['recurrence_type'] ?? 'none') !== 'none') && empty($merged['due_date'])) {
                throw ValidationException::withMessages(['actions' => 'Tarefas com horário ou recorrência precisam de uma data.']);
            }
            // The existing task service updates date and time together.
            if (array_key_exists('due_time', $data) || array_key_exists('due_date', $data)) {
                $data['due_date'] = $merged['due_date'];
                $data['due_time'] = $merged['due_time'] ?? null;
            }
        }
        if ($entity === 'habit' && ($merged['frequency_type'] ?? '') === 'custom' && empty($merged['target_days'])) {
            throw ValidationException::withMessages(['actions' => 'Hábitos personalizados precisam dos dias da semana.']);
        }

        return $data;
    }

    /** Call inside the same transaction that marks the proposal applied. */
    public function apply(User $user, array $actions): array
    {
        $models = [];
        // Lock and check every existing record before making the first change.
        foreach ($actions as $i => $action) {
            if ($action['operation'] === 'create') {
                continue;
            }
            $model = self::MODELS[$action['entity']]::where('user_id', $user->id)->lockForUpdate()->find($action['id']);
            abort_unless($model && hash_equals($action['fingerprint'], $this->fingerprint($model)), 409,
                'Um item mudou desde a proposta. Peça ao assistente para atualizar o plano.');
            $models[$i] = $model;
        }

        $results = [];
        foreach ($actions as $i => $action) {
            ['entity' => $entity, 'operation' => $operation, 'data' => $data] = $action;
            $timezone = $action['timezone'];
            $model = $models[$i] ?? null;
            if ($operation === 'delete') {
                match ($entity) {
                    'task' => $this->tasks->delete($model),
                    'habit' => $this->habits->delete($model),
                    'event' => $this->calendar->delete($model),
                };
            } elseif ($operation === 'create') {
                $model = match ($entity) {
                    'task' => $this->tasks->create($user, TaskDTO::fromArray($data)),
                    'habit' => $this->habits->create($user, HabitDTO::fromArray($data)),
                    'event' => $this->calendar->create($user, CalendarEventDTO::fromArray($this->eventDatesToUtc($data, $timezone))),
                };
            } else {
                // Preserve fields not exposed to the assistant (colors, recurrence rules, lists...).
                $merged = array_replace($model->attributesToArray(), $this->snapshot($model, $entity, $timezone), $data);
                $model = match ($entity) {
                    'task' => $this->tasks->update($model, TaskDTO::fromArray($data)),
                    'habit' => $this->habits->update($model, HabitDTO::fromArray($merged)),
                    'event' => $this->calendar->update($model, CalendarEventDTO::fromArray($this->eventDatesToUtc($merged, $timezone))),
                };
                // HabitService's normal update deliberately omits start_date.
                if ($entity === 'habit' && isset($data['start_date'])) {
                    $model->update(['start_date' => $data['start_date']]);
                }
            }
            $results[] = ['entity' => $entity, 'operation' => $operation, 'id' => $model->id];
        }

        return $results;
    }

    public function schema(): array
    {
        $variants = [];
        foreach (array_keys(self::MODELS) as $entity) {
            $properties = [];
            foreach ($this->fields($entity) as $field) {
                $properties[$field] = match ($field) {
                    'estimated_minutes', 'target_frequency' => ['type' => ['integer', 'null']],
                    'is_all_day' => ['type' => ['boolean', 'null']],
                    'target_days' => ['type' => ['array', 'null'], 'items' => ['type' => 'integer']],
                    default => ['type' => ['string', 'null']],
                };
            }
            foreach (['create', 'update', 'delete'] as $operation) {
                $variants[] = $this->objectSchema([
                    'entity' => ['type' => 'string', 'enum' => [$entity]],
                    'operation' => ['type' => 'string', 'enum' => [$operation]],
                    'id' => ['type' => ['string', 'null']],
                    'data' => $this->objectSchema($operation === 'delete' ? [] : $properties),
                ]);
            }
        }

        return $this->objectSchema([
            'message' => ['type' => 'string'],
            'actions' => ['type' => 'array', 'items' => ['anyOf' => $variants]],
        ]);
    }

    private function eventDatesToUtc(array $data, string $timezone): array
    {
        foreach (['start_date', 'end_date'] as $field) {
            $data[$field] = CarbonImmutable::parse($data[$field], $timezone)->utc()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    private function objectSchema(array $properties): array
    {
        return ['type' => 'object', 'properties' => (object) $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }
}

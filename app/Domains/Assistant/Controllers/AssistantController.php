<?php

namespace App\Domains\Assistant\Controllers;

use App\Domains\Assistant\Models\Conversation;
use App\Domains\Assistant\Requests\SendMessageRequest;
use App\Domains\Assistant\Services\AssistantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function status(): JsonResponse
    {
        return $this->success(['configured' => (bool) config('assistant.api_key')]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = Conversation::where('user_id', $request->user()->id)->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);

        return $this->success(['items' => $page->items(), 'has_more' => $page->hasMorePages()]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->created(Conversation::create(['user_id' => $request->user()->id, 'title' => 'Nova conversa']));
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->owner($request, $conversation);

        return $this->success($conversation->load(['messages' => fn ($q) => $q->orderBy('created_at')->orderBy('id')]));
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->owner($request, $conversation);

        return $this->success($this->assistant->send($request->user(), $conversation, $request->validated()));
    }

    public function approve(Request $request, Conversation $conversation, string $message): JsonResponse
    {
        $this->owner($request, $conversation);
        $request->validate(['confirmed' => ['required', 'accepted']]);

        return $this->success($this->assistant->decide($request->user(), $conversation, $message, true));
    }

    public function reject(Request $request, Conversation $conversation, string $message): JsonResponse
    {
        $this->owner($request, $conversation);

        return $this->success($this->assistant->decide($request->user(), $conversation, $message, false));
    }

    private function owner(Request $request, Conversation $conversation): void
    {
        $this->authorize('view', $conversation);
    }
}

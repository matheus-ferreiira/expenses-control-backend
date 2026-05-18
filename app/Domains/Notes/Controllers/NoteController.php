<?php

namespace App\Domains\Notes\Controllers;

use App\Domains\Notes\DTOs\NoteDTO;
use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Requests\FavoriteNoteRequest;
use App\Domains\Notes\Requests\NoteFilterRequest;
use App\Domains\Notes\Requests\PinNoteRequest;
use App\Domains\Notes\Requests\StoreNoteRequest;
use App\Domains\Notes\Requests\UpdateNoteRequest;
use App\Domains\Notes\Resources\NoteResource;
use App\Domains\Notes\Services\NoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    public function index(NoteFilterRequest $request): JsonResponse
    {
        $notes = $this->noteService->list($request->user(), $request->validated());

        return $this->paginatedSuccess(NoteResource::collection($notes));
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $this->noteService->create(
            $request->user(),
            NoteDTO::fromArray($request->validated()),
        );

        return $this->created(new NoteResource($note), 'Note created');
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        $this->noteService->touch($note);

        return $this->success(new NoteResource($note->load('tags')));
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note = $this->noteService->update($note, NoteDTO::fromArray($request->validated()));

        return $this->success(new NoteResource($note), 'Note updated');
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->authorize('delete', $note);
        $this->noteService->delete($note);

        return $this->noContent();
    }

    public function pin(PinNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note = $this->noteService->pin($note, (bool) $request->validated()['pinned']);

        return $this->success(new NoteResource($note), 'Note pin updated');
    }

    public function favorite(FavoriteNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note = $this->noteService->favorite($note, (bool) $request->validated()['favorited']);

        return $this->success(new NoteResource($note), 'Note favorite updated');
    }

    public function archive(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note = $this->noteService->archive($note, true);

        return $this->success(new NoteResource($note), 'Note archived');
    }

    public function unarchive(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note = $this->noteService->archive($note, false);

        return $this->success(new NoteResource($note), 'Note unarchived');
    }
}

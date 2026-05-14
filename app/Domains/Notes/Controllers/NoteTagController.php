<?php

namespace App\Domains\Notes\Controllers;

use App\Domains\Notes\Models\NoteTag;
use App\Domains\Notes\Requests\StoreNoteTagRequest;
use App\Domains\Notes\Resources\NoteTagResource;
use App\Domains\Notes\Services\NoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteTagController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tags = $this->noteService->listTags($request->user());

        return $this->success(NoteTagResource::collection($tags));
    }

    public function store(StoreNoteTagRequest $request): JsonResponse
    {
        $tag = $this->noteService->createTag($request->user(), $request->validated());

        return $this->created(new NoteTagResource($tag), 'Tag created');
    }

    public function update(StoreNoteTagRequest $request, NoteTag $noteTag): JsonResponse
    {
        $this->authorize('update', $noteTag);
        $tag = $this->noteService->updateTag($noteTag, $request->validated());

        return $this->success(new NoteTagResource($tag), 'Tag updated');
    }

    public function destroy(Request $request, NoteTag $noteTag): JsonResponse
    {
        $this->authorize('delete', $noteTag);
        $this->noteService->deleteTag($noteTag);

        return $this->noContent();
    }
}

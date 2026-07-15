<?php

namespace App\Domains\Document\Controllers;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Requests\StoreDocumentCategoryRequest;
use App\Domains\Document\Requests\UpdateDocumentCategoryRequest;
use App\Domains\Document\Resources\DocumentCategoryResource;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentCategoryController extends ApiController
{
    protected ?string $model = DocumentCategory::class;

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = DocumentCategory::query()
            ->when($type = $request->input('type'), function ($query) use ($type) {
                $resolved = DocumentCategory::resolveType($type);
                if ($resolved) {
                    $query->byType($resolved);
                }
            })
            ->withCount('documents')
            ->orderBy('sort_order')
            ->get();

        return DocumentCategoryResource::collection($categories);
    }

    public function store(StoreDocumentCategoryRequest $request): DocumentCategoryResource
    {
        $data = $request->validated();

        $data['documentable_type'] = DocumentCategory::resolveType($data['documentable_type']);

        $category = DocumentCategory::create($data);

        return new DocumentCategoryResource($category);
    }

    public function show(DocumentCategory $documentCategory): DocumentCategoryResource
    {
        $documentCategory->loadCount('documents');

        return new DocumentCategoryResource($documentCategory);
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $documentCategory): DocumentCategoryResource
    {
        $documentCategory->update($request->validated());

        return new DocumentCategoryResource($documentCategory);
    }

    public function destroy(DocumentCategory $documentCategory): JsonResponse
    {
        $documentCategory->delete();

        return response()->json(['message' => __('document.category_deleted')]);
    }
}

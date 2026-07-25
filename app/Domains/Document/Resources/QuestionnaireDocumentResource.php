<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** @mixin Document */
class QuestionnaireDocumentResource extends DocumentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['url'] = route('questionnaire.documents.serve', [
            'documentId' => $this->id,
        ]);

        $data['thumbnail_url'] = URL::temporarySignedRoute(
            'questionnaire.documents.serve',
            now()->addHours(24),
            array_filter([
                'documentId' => $this->id,
                'thumbnail' => $this->thumbnail_path ? 1 : null,
            ]),
        );

        $data['meta'] = $this->meta;

        return $data;
    }
}

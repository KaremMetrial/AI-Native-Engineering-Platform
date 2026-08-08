<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateArtifactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
        ];
    }
}

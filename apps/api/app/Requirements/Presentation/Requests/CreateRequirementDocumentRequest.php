<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Requests;

use App\Requirements\Domain\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateRequirementDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid'],
            'type' => ['required', new Enum(DocumentType::class)],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}

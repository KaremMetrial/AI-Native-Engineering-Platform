<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Requests;

use App\Graph\Domain\ApprovalDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ApproveArtifactVersionRequest extends FormRequest
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
            'decision' => ['required', new Enum(ApprovalDecision::class)],
            'comment' => ['nullable', 'string'],
        ];
    }
}

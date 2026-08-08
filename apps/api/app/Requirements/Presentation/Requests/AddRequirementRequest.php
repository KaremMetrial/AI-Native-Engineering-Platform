<?php

declare(strict_types=1);

namespace App\Requirements\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddRequirementRequest extends FormRequest
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
            'text' => ['required', 'string'],
            'acceptance_criteria' => ['nullable', 'array'],
            'acceptance_criteria.*' => ['string'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptureConstraintRequest extends FormRequest
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
            'statement' => ['required', 'string'],
        ];
    }
}

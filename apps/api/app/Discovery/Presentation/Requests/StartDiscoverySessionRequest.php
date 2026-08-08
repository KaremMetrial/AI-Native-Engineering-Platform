<?php

declare(strict_types=1);

namespace App\Discovery\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDiscoverySessionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}

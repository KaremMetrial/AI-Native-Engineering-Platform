<?php

declare(strict_types=1);

namespace App\Graph\Presentation\Requests;

use App\Graph\Domain\LinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LinkArtifactVersionsRequest extends FormRequest
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
            'from_version_id' => ['required', 'uuid'],
            'to_version_id' => ['required', 'uuid'],
            'link_type' => ['required', new Enum(LinkType::class)],
        ];
    }
}

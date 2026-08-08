<?php

declare(strict_types=1);

namespace App\AiOrchestration\Presentation\Requests;

use App\AiOrchestration\Domain\StreamingCapability;
use App\AiOrchestration\Domain\StructuredOutputCapability;
use App\AiOrchestration\Domain\ToolUseCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RequestGenerationRequest extends FormRequest
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
            'workflow_name' => ['required', 'string', 'max:255'],
            'structured_output' => ['required', new Enum(StructuredOutputCapability::class)],
            'tool_use' => ['required', new Enum(ToolUseCapability::class)],
            'streaming' => ['required', new Enum(StreamingCapability::class)],
            'min_context_window' => ['required', 'integer', 'min:1'],
            'requires_vision' => ['nullable', 'boolean'],
            'requires_deterministic_seed' => ['nullable', 'boolean'],
        ];
    }
}

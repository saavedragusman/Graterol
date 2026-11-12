<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-06: toda recepción se pesa; valida que cada línea traiga su peso real.
 */
class ConfirmReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receive', $this->route('purchase')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.received_weight_kg' => ['required', 'numeric', 'gt:0'],
            'lines.*.received_pieces' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

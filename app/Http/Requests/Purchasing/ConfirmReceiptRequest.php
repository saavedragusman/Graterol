<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-06: toda recepción se pesa; valida que cada línea traiga su peso real
 * y que no falte ni sobre ninguna línea de la compra.
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $purchase = $this->route('purchase');
            /** @var array<int, array{id?: int}> $lines */
            $lines = $this->input('lines', []);
            $submittedIds = collect($lines)->pluck('id')->filter()->sort()->values();
            $expectedIds = $purchase?->lines->pluck('id')->sort()->values() ?? collect();

            if ($submittedIds->all() !== $expectedIds->all()) {
                $validator->errors()->add('lines', 'Hay que pesar todas las líneas de la compra, ni una de más ni una de menos.');
            }
        });
    }
}

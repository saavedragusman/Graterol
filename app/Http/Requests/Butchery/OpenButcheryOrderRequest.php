<?php

declare(strict_types=1);

namespace App\Http\Requests\Butchery;

use App\Domain\Butchery\Models\ButcheryOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-09: abre la orden con el lote de entrada y los kg de entrada.
 */
class OpenButcheryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ButcheryOrder::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'input_weight_kg' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

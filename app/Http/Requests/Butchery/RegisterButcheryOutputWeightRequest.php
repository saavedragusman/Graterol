<?php

declare(strict_types=1);

namespace App\Http\Requests\Butchery;

use App\Domain\Butchery\Models\ButcheryOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-09: el carnicero pesa cada corte; el peso puede ser 0.
 */
class RegisterButcheryOutputWeightRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ButcheryOrder $order */
        $order = $this->route('order');

        return $this->user()?->can('registerWeight', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actual_weight_kg' => ['required', 'numeric', 'gte:0'],
        ];
    }
}

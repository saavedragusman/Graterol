<?php

declare(strict_types=1);

namespace App\Http\Requests\Butchery;

use App\Domain\Butchery\Models\ButcheryOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BR-10/BR-11/BR-12: cierra la orden; la validación de pesos y el costeo
 * viven en CloseButcheryOrderAction.
 */
class CloseButcheryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ButcheryOrder $order */
        $order = $this->route('order');

        return $this->user()?->can('close', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

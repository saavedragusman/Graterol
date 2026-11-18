<?php

declare(strict_types=1);

namespace App\Http\Controllers\Butchery;

use App\Domain\Butchery\Actions\CloseButcheryOrderAction;
use App\Domain\Butchery\Actions\ListAvailableRawMaterialBatchesAction;
use App\Domain\Butchery\Actions\OpenButcheryOrderAction;
use App\Domain\Butchery\Actions\RegisterButcheryOutputWeightAction;
use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Butchery\CloseButcheryOrderRequest;
use App\Http\Requests\Butchery\OpenButcheryOrderRequest;
use App\Http\Requests\Butchery\RegisterButcheryOutputWeightRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * BR-09 a BR-12: despiece en la PWA. Solo orquesta (C3): la regla y la
 * transacción viven en las Actions de Butchery.
 */
class OrderController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ButcheryOrder::class);

        $openOrders = ButcheryOrder::query()
            ->where('status', 'open')
            ->with(['batch.product', 'butcher'])
            ->orderBy('created_at')
            ->get();

        $availableBatches = (new ListAvailableRawMaterialBatchesAction)->execute()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'code' => $batch->code,
                'product_name' => $batch->product?->name,
                'available_weight_kg' => (string) $batch->getAttribute('balance'),
            ]);

        return Inertia::render('butchery/OrdersIndex', [
            'openOrders' => $openOrders,
            'availableBatches' => $availableBatches,
        ]);
    }

    public function store(OpenButcheryOrderRequest $request): RedirectResponse
    {
        try {
            $order = (new OpenButcheryOrderAction)->execute(
                batchId: (int) $request->validated('batch_id'),
                inputWeightKg: (string) $request->validated('input_weight_kg'),
                butcherId: (int) $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['batch_id' => $e->getMessage()]);
        }

        return redirect()->route('butchery.orders.show', $order);
    }

    public function show(ButcheryOrder $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['batch.product', 'butcher', 'lines.cutProduct']);

        return Inertia::render('butchery/OrderShow', [
            'order' => $order,
            'deviationThresholdPercent' => Setting::valueFor('butchery.deviation_threshold_percent', '2.00'),
        ]);
    }

    public function updateWeight(RegisterButcheryOutputWeightRequest $request, ButcheryOrder $order, int $line): RedirectResponse
    {
        // La línea se busca dentro de las de la orden (no por id suelto): si
        // no pertenece a esta orden, 404 en vez de pesar una línea ajena.
        $orderLine = $order->lines()->findOrFail($line);

        try {
            (new RegisterButcheryOutputWeightAction)->execute($orderLine->id, (string) $request->validated('actual_weight_kg'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['actual_weight_kg' => $e->getMessage()]);
        }

        return redirect()->route('butchery.orders.show', $order);
    }

    public function close(CloseButcheryOrderRequest $request, ButcheryOrder $order): RedirectResponse
    {
        try {
            (new CloseButcheryOrderAction)->execute($order->id, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return redirect()->route('butchery.orders.index')->with('status', 'Orden de despiece cerrada.');
    }
}

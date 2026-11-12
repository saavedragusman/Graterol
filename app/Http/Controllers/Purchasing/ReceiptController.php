<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Actions\ReceivePurchaseAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ConfirmReceiptRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M2/DEC-01: recepción con pesaje en la PWA. Solo orquesta (C3): la regla
 * y la transacción viven en ReceivePurchaseAction.
 */
class ReceiptController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Purchase::class);

        $purchases = Purchase::query()
            ->where('status', 'draft')
            ->with('supplier')
            ->withCount('lines')
            ->orderBy('supplier_invoice_date')
            ->get();

        return Inertia::render('purchasing/ReceiptsIndex', [
            'purchases' => $purchases,
        ]);
    }

    public function show(Purchase $purchase): Response
    {
        Gate::authorize('receive', $purchase);
        abort_if($purchase->status !== 'draft', 404);

        $purchase->load(['supplier', 'lines.product']);

        return Inertia::render('purchasing/ReceiptShow', [
            'purchase' => $purchase,
        ]);
    }

    public function confirm(ConfirmReceiptRequest $request, Purchase $purchase): RedirectResponse
    {
        $receivedByLine = [];

        foreach ($request->validated('lines') as $line) {
            $receivedByLine[$line['id']] = [
                'received_weight_kg' => (string) $line['received_weight_kg'],
                'received_pieces' => $line['received_pieces'] ?? null,
            ];
        }

        (new ReceivePurchaseAction)->execute($purchase, $receivedByLine, $request->user()->id);

        return redirect()->route('purchasing.receipts.index')->with('status', 'Recepción confirmada.');
    }
}

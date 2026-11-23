<?php

use App\Http\Controllers\Butchery\OrderController as ButcheryOrderController;
use App\Http\Controllers\Purchasing\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// M2/DEC-01: recepción con pesaje, PWA (Inertia). Comparte la sesión
// autenticada del panel; la PWA sin conexión completa es de M5.
Route::middleware('auth')->prefix('purchasing/receipts')->name('purchasing.receipts.')->group(function () {
    Route::get('/', [ReceiptController::class, 'index'])->name('index');
    Route::get('/{purchase}', [ReceiptController::class, 'show'])->name('show');
    Route::post('/{purchase}/confirm', [ReceiptController::class, 'confirm'])->name('confirm');
});

// BR-09 a BR-12: despiece, PWA (Inertia). Necesita conexión, igual que la
// recepción de M2 (BR-26 solo cubre ventas).
Route::middleware('auth')->prefix('butchery/orders')->name('butchery.orders.')->group(function () {
    Route::get('/', [ButcheryOrderController::class, 'index'])->name('index');
    Route::post('/', [ButcheryOrderController::class, 'store'])->name('store');
    Route::get('/{order}', [ButcheryOrderController::class, 'show'])->name('show');
    Route::patch('/{order}/lines/{line}', [ButcheryOrderController::class, 'updateWeight'])->name('lines.update');
    Route::post('/{order}/close', [ButcheryOrderController::class, 'close'])->name('close');
});

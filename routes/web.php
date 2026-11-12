<?php

use App\Http\Controllers\Purchasing\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// M2/DEC-01: recepción con pesaje, PWA (Inertia). Comparte la sesión
// autenticada del panel; la PWA sin conexión completa es de M5.
Route::middleware('auth')->prefix('purchasing/receipts')->name('purchasing.receipts.')->group(function () {
    Route::get('/', [ReceiptController::class, 'index'])->name('index');
    Route::get('/{purchase}', [ReceiptController::class, 'show'])->name('show');
    Route::post('/{purchase}/confirm', [ReceiptController::class, 'confirm'])->name('confirm');
});

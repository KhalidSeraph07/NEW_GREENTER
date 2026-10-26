<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\BoletaController;
use Illuminate\Support\Facades\Route;

Route::middleware('h2.operator')->group(function () {
    Route::get('/boletas', [BoletaController::class, 'index'])->name('boletas.index');
    Route::post('/boletas', [BoletaController::class, 'issue'])->name('boletas.store');
    Route::post('/summaries', [BoletaController::class, 'freeze'])->name('summaries.store');
    Route::get('/boletas/{id}', [BoletaController::class, 'showBoleta'])->name('boletas.show');
    Route::get('/summaries/{id}', [BoletaController::class, 'showSummary'])->name('summaries.show');
    Route::post('/summaries/{id}/simulate', [BoletaController::class, 'process'])->name('summaries.simulate');
    Route::get('/h4/{kind}/{id}/evidence/{file}', [BoletaController::class, 'download'])->name('h4.download');
    Route::get('/', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/simulate', [InvoiceController::class, 'simulate'])->name('invoices.simulate');
    Route::get('/invoices/{invoice}/evidence/{kind}', [InvoiceController::class, 'download'])->name('invoices.download');
});

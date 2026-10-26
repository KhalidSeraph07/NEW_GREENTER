<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('h2.operator')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/simulate', [InvoiceController::class, 'simulate'])->name('invoices.simulate');
    Route::get('/invoices/{invoice}/evidence/{kind}', [InvoiceController::class, 'download'])->name('invoices.download');
});

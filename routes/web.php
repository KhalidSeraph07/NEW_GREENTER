<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\BoletaController;
use Illuminate\Support\Facades\Route;

Route::middleware('h2.operator')->group(function () {
    Route::get('/invoice-notes', [\App\Http\Controllers\InvoiceNoteController::class, 'index'])->name('notes.index');
    Route::post('/invoice-notes', [\App\Http\Controllers\InvoiceNoteController::class, 'store'])->name('notes.store');
    Route::get('/invoice-notes/{id}', [\App\Http\Controllers\InvoiceNoteController::class, 'show'])->name('notes.show');
    Route::post('/invoice-notes/{id}/simulate', [\App\Http\Controllers\InvoiceNoteController::class, 'process'])->name('notes.process');
    Route::get('/invoice-notes/{id}/evidence/{kind}', [\App\Http\Controllers\InvoiceNoteController::class, 'download'])->name('notes.download');
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

<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\BoletaController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [\App\Http\Controllers\SessionController::class, 'create'])->name('login');
Route::post('/login', [\App\Http\Controllers\SessionController::class, 'store'])->name('login.store');

Route::middleware('h2.operator')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\SessionController::class, 'destroy'])->name('logout');
    Route::view('/settings', 'settings')->name('settings');
    Route::middleware('h2.operator:ADMIN')->group(function () {
        Route::get('/accounts', [\App\Http\Controllers\AccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store'])->name('accounts.store');
        Route::post('/accounts/{id}/deactivate', [\App\Http\Controllers\AccountController::class, 'deactivate'])->name('accounts.deactivate');
    });
    Route::get('/receipt-notes', [\App\Http\Controllers\BoletaNoteController::class, 'index'])->name('receipt-notes.index');
    Route::post('/receipt-notes', [\App\Http\Controllers\BoletaNoteController::class, 'issue'])->name('receipt-notes.store');
    Route::post('/note-summaries', [\App\Http\Controllers\BoletaNoteController::class, 'freeze'])->name('receipt-notes.freeze');
    Route::get('/receipt-notes/{kind}/{id}', [\App\Http\Controllers\BoletaNoteController::class, 'show'])->name('receipt-notes.show');
    Route::post('/note-summaries/{id}/simulate', [\App\Http\Controllers\BoletaNoteController::class, 'process'])->name('receipt-notes.process');
    Route::get('/receipt-notes/{kind}/{id}/evidence/{file}', [\App\Http\Controllers\BoletaNoteController::class, 'download'])->name('receipt-notes.download');
    Route::get('/invoice-notes', [\App\Http\Controllers\InvoiceNoteController::class, 'index'])->name('notes.index');
    Route::post('/invoice-notes', [\App\Http\Controllers\InvoiceNoteController::class, 'store'])->name('notes.store');
    Route::get('/invoice-notes/{id}', [\App\Http\Controllers\InvoiceNoteController::class, 'show'])->name('notes.show');
    Route::post('/invoice-notes/{id}/simulate', [\App\Http\Controllers\InvoiceNoteController::class, 'process'])->name('notes.process');
    Route::get('/invoice-notes/{id}/evidence/{kind}', [\App\Http\Controllers\InvoiceNoteController::class, 'download'])->name('notes.download');
    Route::get('/boletas', [BoletaController::class, 'index'])->name('boletas.index');
    Route::post('/boletas', [BoletaController::class, 'issue'])->name('boletas.store');
    Route::post('/summaries/review', [BoletaController::class, 'review'])->name('summaries.review');
    Route::post('/summaries/edit-review', [BoletaController::class, 'editReview'])->name('summaries.edit-review');
    Route::post('/summaries', [BoletaController::class, 'freeze'])->name('summaries.store');
    Route::get('/boletas/{id}', [BoletaController::class, 'showBoleta'])->name('boletas.show');
    Route::get('/summaries/{id}', [BoletaController::class, 'showSummary'])->name('summaries.show');
    Route::post('/summaries/{id}/simulate', [BoletaController::class, 'process'])->name('summaries.simulate');
    Route::get('/h4/{kind}/{id}/evidence/{file}', [BoletaController::class, 'download'])->name('h4.download');
    Route::get('/', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices/review', [InvoiceController::class, 'review'])->name('invoices.review');
    Route::post('/invoices/edit-review', [InvoiceController::class, 'editReview'])->name('invoices.edit-review');
    Route::post('/invoices/{invoice}/recover', [InvoiceController::class, 'recover'])->name('invoices.recover');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/simulate', [InvoiceController::class, 'simulate'])->name('invoices.simulate');
    Route::get('/invoices/{invoice}/evidence/{kind}', [InvoiceController::class, 'download'])->name('invoices.download');
});

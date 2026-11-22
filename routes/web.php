<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MasterController;
use App\Http\Middleware\EnsureCurrentAccount;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'create'])->name('login');
    Route::post('/masuk', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', EnsureCurrentAccount::class])->group(function (): void {
    Route::post('/keluar', [AuthController::class, 'destroy'])->name('logout');

    Route::redirect('/master', '/master/produk');
    Route::prefix('master/{section}')->name('master.')->group(function (): void {
        Route::get('/', [MasterController::class, 'index'])->name('index');
        Route::get('/tambah', [MasterController::class, 'create'])->name('create');
        Route::post('/', [MasterController::class, 'store'])->name('store');
        Route::get('/{record}/ubah', [MasterController::class, 'edit'])->whereNumber('record')->name('edit');
        Route::patch('/{record}', [MasterController::class, 'update'])->whereNumber('record')->name('update');
    });

    Route::resourceVerbs(['create' => 'tambah', 'edit' => 'ubah']);
    Route::resource('akun', AccountController::class)
        ->parameters(['akun' => 'account'])
        ->names('accounts')
        ->except(['show', 'destroy']);
});

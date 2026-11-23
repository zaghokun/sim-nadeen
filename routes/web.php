<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
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

    Route::prefix('inventori/{kind}')->name('inventory.')->controller(InventoryController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{item}', 'show')->whereNumber('item')->name('show');
        Route::get('/{item}/masuk', 'receiveForm')->whereNumber('item')->name('receive.form');
        Route::post('/{item}/masuk', 'receive')->whereNumber('item')->name('receive');
        Route::get('/{item}/keluar', 'issueForm')->whereNumber('item')->name('issue.form');
        Route::post('/{item}/keluar', 'issue')->whereNumber('item')->name('issue');
        Route::get('/{item}/lot/{lot}/kondisi', 'lotForm')->whereNumber(['item', 'lot'])->defaults('action', 'kondisi')->name('condition.form');
        Route::post('/{item}/lot/{lot}/kondisi', 'updateLot')->whereNumber(['item', 'lot'])->defaults('action', 'kondisi')->name('condition');
        Route::middleware('can:operate-admin')->group(function (): void {
            Route::get('/{item}/lot/{lot}/nilai', 'lotForm')->whereNumber(['item', 'lot'])->defaults('action', 'nilai')->name('value.form');
            Route::post('/{item}/lot/{lot}/nilai', 'updateLot')->whereNumber(['item', 'lot'])->defaults('action', 'nilai')->name('value');
        });
    });

    Route::prefix('pesanan')->name('orders.')->controller(OrderController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->whereNumber('order')->name('show');
        Route::post('/{order}/kirim', 'transition')->whereNumber('order')->name('shipping');
        Route::post('/{order}/detail/{line}/progres', 'transition')->whereNumber(['order', 'line'])->name('production');
        Route::get('/{order}/detail/{line}/{action}', 'workForm')->whereNumber(['order', 'line'])->whereIn('action', ['pakai', 'hpp'])->name('work.form');
        Route::post('/{order}/detail/{line}/{action}', 'work')->whereNumber(['order', 'line'])->whereIn('action', ['pakai', 'hpp'])->name('work');

        Route::middleware('can:operate-admin')->group(function (): void {
            Route::get('/tambah', 'create')->name('create');
            Route::post('/', 'save')->name('store');
            Route::get('/estimasi', 'estimate')->middleware('throttle:120,1')->name('estimate');
            Route::get('/{order}/ubah', 'edit')->whereNumber('order')->name('edit');
            Route::post('/{order}/ubah', 'save')->whereNumber('order')->name('update');
            Route::post('/{order}/konfirmasi', 'confirm')->whereNumber('order')->name('confirm');
            Route::get('/{order}/batal', 'cancelForm')->whereNumber('order')->name('cancel.form');
            Route::post('/{order}/batal', 'cancel')->whereNumber('order')->name('cancel');
            Route::get('/{order}/nota', 'print')->whereNumber('order')->name('print');
            Route::get('/{order}/bukti/{payment}', 'print')->whereNumber(['order', 'payment'])->name('receipt');
            Route::get('/{order}/detail/{line}/mutasi/{movement}/balik', 'materialCorrection')->whereNumber(['order', 'line', 'movement'])->name('material.reverse.form');
            Route::post('/{order}/detail/{line}/mutasi/{movement}/balik', 'reverseMaterial')->whereNumber(['order', 'line', 'movement'])->name('material.reverse');
        });
    });

    Route::controller(FinanceController::class)->group(function (): void {
        Route::get('/pembelian-bahan', 'purchases')->name('purchases.index');
        Route::get('/pembelian-bahan/tambah', 'purchaseForm')->name('purchases.create');
        Route::post('/pembelian-bahan', 'purchase')->name('purchases.store');
        Route::middleware('can:operate-admin')->group(function (): void {
            Route::get('/pesanan/{order}/bayar', 'paymentForm')->whereNumber('order')->name('payments.create');
            Route::post('/pesanan/{order}/bayar', 'payment')->whereNumber('order')->name('payments.store');
            Route::get('/buku-kas', 'cash')->name('cash.index');
            Route::get('/buku-kas/tambah', 'cashForm')->name('cash.create');
            Route::post('/buku-kas', 'storeCash')->name('cash.store');
            Route::get('/koreksi/{source}/{record}', 'reverseForm')->whereIn('source', ['pembayaran', 'pembelian', 'kas'])->whereNumber('record')->name('finance.reverse.form');
            Route::post('/koreksi/{source}/{record}', 'reverse')->whereIn('source', ['pembayaran', 'pembelian', 'kas'])->whereNumber('record')->name('finance.reverse');
        });
    });

    Route::middleware('can:operate-admin')->controller(ReportController::class)->group(function (): void {
        Route::get('/dashboard', 'dashboard')->name('reports.dashboard');
        Route::get('/laporan/{report?}', 'index')->whereIn('report', ['arus-kas', 'laba-pesanan', 'laba-rugi'])->name('reports.index');
    });
});

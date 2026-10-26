<?php

use App\Http\Controllers\Auth\SsoAuthController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\WhatsAppMediaController;
use App\Http\Controllers\WhatsAppQrController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/integrations/waha/accounts/{account}/qr', WhatsAppQrController::class)
    ->middleware('auth')
    ->name('waha.accounts.qr');

Route::get('/integrations/waha/messages/{message}/media', WhatsAppMediaController::class)
    ->middleware('auth')
    ->name('waha.messages.media');

Route::get('/admin/backups/{filename}/download', BackupDownloadController::class)
    ->middleware('auth')
    ->where('filename', '[A-Za-z0-9._-]+\.sql')
    ->name('backups.download');

Route::middleware(['guest:web', 'throttle:20,1'])->group(function (): void {
    Route::get('/sso/login', [SsoAuthController::class, 'redirect'])->name('sso.login');
    Route::get('/sso/callback', [SsoAuthController::class, 'callback'])->name('sso.callback');
});

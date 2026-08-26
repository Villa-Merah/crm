<?php

use App\Http\Controllers\WhatsAppQrController;
use App\Http\Controllers\WhatsAppMediaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/integrations/waha/accounts/{account}/qr', WhatsAppQrController::class)
    ->middleware('auth')
    ->name('waha.accounts.qr');

Route::get('/integrations/waha/messages/{message}/media', WhatsAppMediaController::class)
    ->middleware('auth')
    ->name('waha.messages.media');

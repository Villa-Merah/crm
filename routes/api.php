<?php

use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Webhooks\WahaWebhookController;
use App\Http\Middleware\VerifyWahaWebhookHmac;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/waha', WahaWebhookController::class)
    ->middleware(VerifyWahaWebhookHmac::class)
    ->name('webhooks.waha');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/students', [StudentController::class, 'index'])
        ->middleware(['public-api:students:read', 'throttle:'.config('public_api.rate_limit').',1'])
        ->name('students.index');
    Route::get('/leads', [LeadController::class, 'index'])
        ->middleware(['public-api:leads:read', 'throttle:'.config('public_api.rate_limit').',1'])
        ->name('leads.index');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])
        ->whereNumber('lead')
        ->middleware(['public-api:leads:read', 'throttle:'.config('public_api.rate_limit').',1'])
        ->name('leads.show');
    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware(['public-api:leads:write', 'api-idempotency', 'throttle:'.config('public_api.rate_limit').',1'])
        ->name('leads.store');
});

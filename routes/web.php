<?php

use App\Http\Middleware\ValidateProxyMasterKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'ElevenLabs Pool API',
        'status' => 'online',
        'auth_required' => !empty(config('elevenlabs.master_key')),
        'pool_status_url' => url('/api/pool/status'),
    ]);
});

// Suporta requisições diretas para /v1/{path} sem prefixo /api (protegidas por chave master)
Route::middleware([ValidateProxyMasterKey::class])->group(function () {
    Route::any('/v1/{path}', [\App\Http\Controllers\Api\ElevenLabsProxyController::class, 'handle'])
        ->where('path', '.*');
});

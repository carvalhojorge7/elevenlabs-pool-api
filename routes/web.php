<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'ElevenLabs Pool API',
        'status' => 'online',
        'pool_status_url' => url('/api/pool/status'),
    ]);
});

// Suporta requisições diretas para /v1/{path} sem prefixo /api
Route::any('/v1/{path}', [\App\Http\Controllers\Api\ElevenLabsProxyController::class, 'handle'])
    ->where('path', '.*');

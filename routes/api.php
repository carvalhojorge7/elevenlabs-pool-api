<?php

use App\Http\Controllers\Api\ElevenLabsProxyController;
use App\Http\Middleware\ValidateProxyMasterKey;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas Protegidas pela Chave Master do Proxy
|--------------------------------------------------------------------------
*/
Route::middleware([ValidateProxyMasterKey::class])->group(function () {

    // Gestão e Monitoramento do Pool
    Route::prefix('pool')->group(function () {
        Route::get('/status', [ElevenLabsProxyController::class, 'poolStatus']);
        Route::post('/reset-cooldown', [ElevenLabsProxyController::class, 'resetCooldown']);
    });

    // Rota Proxy Wildcard para a API da ElevenLabs
    Route::any('/v1/{path}', [ElevenLabsProxyController::class, 'handle'])
        ->where('path', '.*');
});

<?php

use App\Http\Controllers\Api\ElevenLabsProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Gestão e Monitoramento do Pool
|--------------------------------------------------------------------------
*/
Route::prefix('pool')->group(function () {
    Route::get('/status', [ElevenLabsProxyController::class, 'poolStatus']);
    Route::post('/reset-cooldown', [ElevenLabsProxyController::class, 'resetCooldown']);
});

/*
|--------------------------------------------------------------------------
| Rota Proxy Wildcard para a API da ElevenLabs
|--------------------------------------------------------------------------
| Espelha qualquer requisição /api/v1/{path} ou /v1/{path}
| mantendo os mesmos métodos HTTP e parâmetros.
*/
Route::any('/v1/{path}', [ElevenLabsProxyController::class, 'handle'])
    ->where('path', '.*');

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ElevenLabs\ElevenLabsProxyService;
use App\Services\ElevenLabs\KeyPoolManager;
use Illuminate\Http\Request;

class ElevenLabsProxyController extends Controller
{
    protected ElevenLabsProxyService $proxyService;
    protected KeyPoolManager $poolManager;

    public function __construct(ElevenLabsProxyService $proxyService, KeyPoolManager $poolManager)
    {
        $this->proxyService = $proxyService;
        $this->poolManager = $poolManager;
    }

    /**
     * Captura qualquer requisição enviada para /v1/{path} e encaminha via Proxy.
     */
    public function handle(Request $request, string $path)
    {
        return $this->proxyService->proxy($request, "v1/{$path}");
    }

    /**
     * Retorna o status de saúde e saldo de cada chave do pool.
     */
    public function poolStatus()
    {
        return response()->json($this->poolManager->getPoolStatus());
    }

    /**
     * Remove o cooldown de uma chave ou de todas as chaves.
     */
    public function resetCooldown(Request $request)
    {
        $allKeys = $this->poolManager->getAllKeys();
        foreach ($allKeys as $key) {
            $this->poolManager->clearKeyCooldown($key);
        }

        return response()->json([
            'message' => 'Cooldowns de todas as chaves foram reiniciados com sucesso.',
        ]);
    }
}

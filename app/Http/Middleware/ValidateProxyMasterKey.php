<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateProxyMasterKey
{
    /**
     * Valida se a requisição possui a Chave Master configurada para o Proxy.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $masterKey = config('elevenlabs.master_key');

        // Se nenhuma chave master estiver configurada, o proxy permanece aberto
        if (empty($masterKey)) {
            return $next($request);
        }

        // Obtém a chave enviada pelo cliente via diferentes padrões compatíveis:
        // 1. Header padrão da ElevenLabs: xi-api-key
        // 2. Header comum de APIs: X-Api-Key
        // 3. Header padrão HTTP: Authorization: Bearer <key>
        // 4. Query param (opcional para testes rápidos): ?api_key=<key>
        $providedKey = $request->header('xi-api-key')
            ?: $request->header('X-Api-Key')
            ?: $request->bearerToken()
            ?: $request->query('api_key');

        if (!$providedKey || !hash_equals((string) $masterKey, (string) $providedKey)) {
            return response()->json([
                'detail' => [
                    'status' => 'unauthorized',
                    'message' => 'Acesso não autorizado. É obrigatório fornecer uma Chave Master válida no cabeçalho [xi-api-key], [X-Api-Key] ou [Authorization: Bearer <key>].',
                ]
            ], 401);
        }

        return $next($request);
    }
}

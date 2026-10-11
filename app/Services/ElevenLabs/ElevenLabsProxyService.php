<?php

namespace App\Services\ElevenLabs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ElevenLabsProxyService
{
    protected KeyPoolManager $poolManager;
    protected string $baseUrl;
    protected int $timeout;
    protected Client $httpClient;

    public function __construct(KeyPoolManager $poolManager)
    {
        $this->poolManager = $poolManager;
        $this->baseUrl = rtrim(config('elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');
        $this->timeout = (int) config('elevenlabs.timeout', 60);
        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'http_errors' => false, // Não lança exception para status 4xx/5xx para podermos repassar
        ]);
    }

    /**
     * Encaminha uma requisição recebida para a API da ElevenLabs com rotação e failover.
     */
    public function proxy(Request $request, string $path)
    {
        $availableKeys = $this->poolManager->getAvailableKeys();
        $totalKeys = count($this->poolManager->getAllKeys());

        if (empty($availableKeys)) {
            return response()->json([
                'detail' => [
                    'status' => 'no_available_keys',
                    'message' => 'Todas as chaves da pool da ElevenLabs estão em cooldown ou nenhuma chave foi configurada.',
                    'total_keys_configured' => $totalKeys,
                ]
            ], 503);
        }

        $method = $request->method();
        $targetUrl = "{$this->baseUrl}/" . ltrim($path, '/');
        if ($queryString = $request->getQueryString()) {
            $targetUrl .= "?{$queryString}";
        }

        // Tentamos despachar utilizando rotação e failover
        $attempts = 0;
        $maxAttempts = count($availableKeys);

        while ($attempts < $maxAttempts) {
            $attempts++;
            $apiKey = $this->poolManager->getNextKey();

            if (!$apiKey) {
                break;
            }

            try {
                // Prepara headers repassando Content-Type e Accept, substituindo a xi-api-key
                $headers = $this->prepareHeaders($request, $apiKey);
                $body = $request->getContent();

                $psrRequest = new Psr7Request($method, $targetUrl, $headers, $body);
                $response = $this->httpClient->send($psrRequest, [
                    'stream' => true,
                ]);

                $statusCode = $response->getStatusCode();

                // Se houver exaustão de cota (429) ou erro de autorização/créditos da chave (401), marca cooldown e tenta a próxima
                if ($statusCode === 429 || ($statusCode === 401 && $maxAttempts > 1)) {
                    $reason = "Status {$statusCode}: " . ($statusCode === 429 ? 'Rate limit / Quota exceeded' : 'Invalid key / Unauthorized');
                    $this->poolManager->markKeyCooldown($apiKey, $reason);
                    Log::warning("[ElevenLabsProxy] Chave {$this->poolManager->maskKey($apiKey)} falhou com status {$statusCode}. Tentando failover ({$attempts}/{$maxAttempts}).");
                    continue;
                }

                return $this->formatClientResponse($response, $apiKey);
            } catch (\Throwable $e) {
                Log::error("[ElevenLabsProxy] Erro de rede ao comunicar com ElevenLabs com a chave {$this->poolManager->maskKey($apiKey)}: " . $e->getMessage());

                if ($attempts >= $maxAttempts) {
                    return response()->json([
                        'detail' => [
                            'message' => 'Erro de conexão com o servidor da ElevenLabs.',
                            'error' => $e->getMessage(),
                        ]
                    ], 502);
                }
            }
        }

        return response()->json([
            'detail' => [
                'message' => 'Todas as chaves disponíveis falharam ou atingiram seus limites.',
                'attempts' => $attempts,
            ]
        ], 429);
    }

    /**
     * Filtra e prepara cabeçalhos da requisição cliente para a ElevenLabs.
     */
    protected function prepareHeaders(Request $request, string $apiKey): array
    {
        $headers = [];

        // Headers essenciais a repassar
        $allowedHeaders = ['content-type', 'accept', 'user-agent'];
        foreach ($request->headers->all() as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, $allowedHeaders)) {
                $headers[$name] = implode(', ', $values);
            }
        }

        // Força a injeção da chave rotacionada da pool
        $headers['xi-api-key'] = $apiKey;

        return $headers;
    }

    /**
     * Converte a resposta PSR-7 da ElevenLabs em resposta Laravel (suportando stream binário de áudio).
     */
    protected function formatClientResponse($psrResponse, string $usedKey)
    {
        $statusCode = $psrResponse->getStatusCode();
        $stream = $psrResponse->getBody();
        $headers = [];

        // Repassar cabeçalhos úteis (Content-Type, Content-Disposition, etc.)
        $forwardHeaders = ['content-type', 'content-length', 'content-disposition', 'request-characters'];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            if (in_array(strtolower($name), $forwardHeaders)) {
                $headers[$name] = implode(', ', $values);
            }
        }

        // Adiciona cabeçalho informativo de qual chave atendeu
        $headers['X-ElevenLabs-Key-Used'] = $this->poolManager->maskKey($usedKey);

        return new StreamedResponse(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, $statusCode, $headers);
    }
}

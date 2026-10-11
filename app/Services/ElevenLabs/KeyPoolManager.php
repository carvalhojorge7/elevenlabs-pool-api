<?php

namespace App\Services\ElevenLabs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeyPoolManager
{
    protected array $keys = [];
    protected string $baseUrl;
    protected int $cooldownMinutes;

    public function __construct()
    {
        $this->keys = config('elevenlabs.keys', []);
        $this->baseUrl = rtrim(config('elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');
        $this->cooldownMinutes = (int) config('elevenlabs.cooldown_minutes', 30);
    }

    /**
     * Retorna todas as chaves configuradas.
     */
    public function getAllKeys(): array
    {
        return $this->keys;
    }

    /**
     * Retorna apenas as chaves ativas (que não estão em cooldown/bloqueadas).
     */
    public function getAvailableKeys(): array
    {
        return array_values(array_filter($this->keys, function ($key) {
            return !$this->isKeyInCooldown($key);
        }));
    }

    /**
     * Obtém a próxima chave disponível utilizando Round-Robin.
     * Se todas as chaves estiverem em cooldown, retorna null.
     */
    public function getNextKey(): ?string
    {
        $available = $this->getAvailableKeys();

        if (empty($available)) {
            return null;
        }

        $counterKey = 'elevenlabs_pool_round_robin_index';
        $index = Cache::get($counterKey, 0);

        // Garante que o índice esteja dentro do range atual de chaves disponíveis
        $selectedKey = $available[$index % count($available)];

        // Incrementa o índice no cache para a próxima chamada
        Cache::forever($counterKey, ($index + 1) % 1000000);

        // Incrementa o contador de requisições atendidas por esta chave
        $this->incrementKeyUsage($selectedKey);

        return $selectedKey;
    }

    /**
     * Marca uma chave como esgotada/em cooldown temporário (ex: erro 429 ou 401).
     */
    public function markKeyCooldown(string $key, ?string $reason = null): void
    {
        $cacheKey = $this->getCooldownCacheKey($key);
        Cache::put($cacheKey, [
            'reason' => $reason ?? 'Quota exceeded / rate limited',
            'blocked_at' => now()->toIso8601String(),
        ], now()->addMinutes($this->cooldownMinutes));

        Log::warning("[KeyPoolManager] Chave colocada em cooldown por {$this->cooldownMinutes}m. Reason: {$reason}", [
            'masked_key' => $this->maskKey($key),
        ]);
    }

    /**
     * Remove uma chave do cooldown manualmente.
     */
    public function clearKeyCooldown(string $key): void
    {
        Cache::forget($this->getCooldownCacheKey($key));
    }

    /**
     * Verifica se a chave está em cooldown.
     */
    public function isKeyInCooldown(string $key): bool
    {
        return Cache::has($this->getCooldownCacheKey($key));
    }

    /**
     * Retorna o motivo e momento em que a chave entrou em cooldown.
     */
    public function getKeyCooldownInfo(string $key): ?array
    {
        return Cache::get($this->getCooldownCacheKey($key));
    }

    /**
     * Incrementa o total de requisições realizadas com a chave.
     */
    public function incrementKeyUsage(string $key): void
    {
        $cacheKey = 'elevenlabs_key_usage_' . md5($key);
        Cache::increment($cacheKey);
    }

    /**
     * Retorna o total de requisições realizadas com a chave.
     */
    public function getKeyUsage(string $key): int
    {
        $cacheKey = 'elevenlabs_key_usage_' . md5($key);
        return (int) Cache::get($cacheKey, 0);
    }

    /**
     * Consulta o saldo/assinatura real da chave diretamente na API da ElevenLabs.
     */
    public function fetchSubscriptionInfo(string $key): array
    {
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $key,
                'Accept' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/v1/user/subscription");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retorna o status geral de todas as chaves do pool com saldo e uso.
     */
    public function getPoolStatus(): array
    {
        $report = [];

        foreach ($this->keys as $key) {
            $isCooldown = $this->isKeyInCooldown($key);
            $sub = $this->fetchSubscriptionInfo($key);

            $characterCount = null;
            $characterLimit = null;
            $tier = null;

            if ($sub['success'] && isset($sub['data'])) {
                $characterCount = $sub['data']['character_count'] ?? null;
                $characterLimit = $sub['data']['character_limit'] ?? null;
                $tier = $sub['data']['tier'] ?? null;
            }

            $report[] = [
                'key_masked' => $this->maskKey($key),
                'status' => $isCooldown ? 'cooldown' : ($sub['success'] ? 'active' : 'invalid_or_error'),
                'cooldown_info' => $this->getKeyCooldownInfo($key),
                'requests_count' => $this->getKeyUsage($key),
                'tier' => $tier,
                'character_count' => $characterCount,
                'character_limit' => $characterLimit,
                'remaining_characters' => ($characterLimit !== null && $characterCount !== null)
                    ? max(0, $characterLimit - $characterCount)
                    : null,
            ];
        }

        return [
            'total_keys' => count($this->keys),
            'available_keys_count' => count($this->getAvailableKeys()),
            'keys' => $report,
        ];
    }

    /**
     * Mascara a chave para exibição segura em logs e endpoints públicos.
     */
    public function maskKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return '***';
        }
        return substr($key, 0, 4) . '...' . substr($key, -4);
    }

    protected function getCooldownCacheKey(string $key): string
    {
        return 'elevenlabs_cooldown_' . md5($key);
    }
}

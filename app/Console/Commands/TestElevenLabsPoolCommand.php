<?php

namespace App\Console\Commands;

use App\Services\ElevenLabs\KeyPoolManager;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestElevenLabsPoolCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elevenlabs:test-pool 
                            {--iterations=2 : Número de requisições de áudio para testar rotação}
                            {--voice= : ID da voz (se não informado, usa a padrão configurada)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa teste real com chaves da ElevenLabs: consulta de saldo, rotação e geração de áudio pt-BR com autoincremento e palavras difíceis.';

    /**
     * Lista de palavras em português de pronúncia desafiadora.
     */
    protected array $hardWords = [
        'Otorrinolaringologista',
        'Anticonstitucionalissimamente',
        'Pneumoultramicroscopicossilicovulcanoconiose',
        'Inconstitucionalissimamente',
        'Desoxirribonucleico',
        'Paralelepípedo',
        'Proparoxítona',
        'Ictiofagia',
        'Estapafúrdio',
        'Plurissignificativo',
    ];

    public function handle(KeyPoolManager $poolManager): int
    {
        $this->info("==========================================================");
        $this->info("   🧪 TESTE REAL DA ELEVENLABS POOL API (SEM FAKES)       ");
        $this->info("==========================================================");

        $keys = $poolManager->getAllKeys();

        if (empty($keys)) {
            $this->error("❌ Nenhuma chave configurada em ELEVENLABS_API_KEYS no arquivo .env!");
            $this->warn("Por favor, adicione suas chaves separadas por vírgula no arquivo .env:");
            $this->line("ELEVENLABS_API_KEYS=chave_1,chave_2");
            return 1;
        }

        $this->line("Total de chaves configuradas: " . count($keys));
        $this->newLine();

        // 1. Obtenção e Exibição de Saldo em cada chave real
        $this->info("📊 ETAPA 1: Consultando saldo e assinatura de cada chave...");
        $tableRows = [];

        foreach ($keys as $index => $key) {
            $masked = $poolManager->maskKey($key);
            $this->line("-> Verificando chave #".($index + 1)." ({$masked})...");

            $sub = $poolManager->fetchSubscriptionInfo($key);

            if ($sub['success']) {
                $data = $sub['data'];
                $tier = $data['tier'] ?? 'N/A';
                $used = $data['character_count'] ?? 0;
                $limit = $data['character_limit'] ?? 0;
                $remaining = max(0, $limit - $used);
                $status = ($data['status'] ?? 'active');

                $tableRows[] = [
                    'Chave' => $masked,
                    'Plano / Tier' => $tier,
                    'Caracteres Usados' => number_format($used, 0, ',', '.'),
                    'Limite' => number_format($limit, 0, ',', '.'),
                    'Saldo Restante' => number_format($remaining, 0, ',', '.'),
                    'Status' => $status,
                ];
            } else {
                $errorMsg = is_array($sub['error']) ? json_encode($sub['error']) : (string)$sub['error'];
                $tableRows[] = [
                    'Chave' => $masked,
                    'Plano / Tier' => 'ERRO',
                    'Caracteres Usados' => 'N/A',
                    'Limite' => 'N/A',
                    'Saldo Restante' => 'N/A',
                    'Status' => "HTTP {$sub['status']}: " . substr($errorMsg, 0, 30),
                ];
            }
        }

        $this->table(
            ['Chave', 'Plano / Tier', 'Caracteres Usados', 'Limite', 'Saldo Restante', 'Status'],
            $tableRows
        );
        $this->newLine();

        // 2. Validação da Voz Selecionada
        $voiceId = $this->option('voice') ?: config('elevenlabs.default_voice_id', 'eUAnqvLQWNX29twcYLUM');
        $modelId = config('elevenlabs.default_model_id', 'eleven_multilingual_v2');

        $this->info("🎙️ ETAPA 2: Configuração de Voz e Modelo");
        $this->line("Voice ID: <comment>{$voiceId}</comment>");
        $this->line("Model ID: <comment>{$modelId}</comment>");
        $this->newLine();

        // 3. Teste de Rotação com Síntese Real de Áudio e Palavras Difíceis em PT-BR
        $iterations = (int) $this->option('iterations');
        $this->info("🔊 ETAPA 3: Gerando {$iterations} áudios reais para testar a rotação...");

        $outputDir = storage_path('app/testing_audio');
        File::ensureDirectoryExists($outputDir);

        $httpClient = new Client([
            'base_uri' => rtrim(config('elevenlabs.base_url', 'https://api.elevenlabs.io'), '/') . '/',
            'timeout' => 60,
        ]);

        for ($i = 1; $i <= $iterations; $i++) {
            $selectedKey = $poolManager->getNextKey();

            if (!$selectedKey) {
                $this->error("❌ Nenhuma chave disponível para o teste #{$i}!");
                break;
            }

            // Sorteia palavra difícil
            $randomWord = $this->hardWords[array_rand($this->hardWords)];
            $text = "Este é o teste número {$i} e a palavra chave é: {$randomWord}.";

            $this->line("----------------------------------------------------------");
            $this->info("▶️ Teste #{$i}:");
            $this->line("Chave Selecionada (Pool): <fg=cyan>{$poolManager->maskKey($selectedKey)}</>");
            $this->line("Texto: <comment>\"{$text}\"</comment>");

            try {
                $response = $httpClient->post("v1/text-to-speech/{$voiceId}", [
                    'headers' => [
                        'xi-api-key' => $selectedKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'audio/mpeg',
                    ],
                    'json' => [
                        'text' => $text,
                        'model_id' => $modelId,
                        'voice_settings' => [
                            'stability' => 0.5,
                            'similarity_boost' => 0.75,
                        ],
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $audioContent = (string) $response->getBody();
                    $filename = "teste_audio_{$i}_{$randomWord}.mp3";
                    $filepath = "{$outputDir}/{$filename}";
                    file_put_contents($filepath, $audioContent);

                    $sizeKb = round(strlen($audioContent) / 1024, 2);
                    $this->info("✅ Áudio gerado com sucesso! ({$sizeKb} KB)");
                    $this->line("📁 Salvo em: <fg=green>{$filepath}</>");
                } else {
                    $this->error("⚠️ Status HTTP {$response->getStatusCode()}: " . (string)$response->getBody());
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $body = $response ? (string)$response->getBody() : $e->getMessage();

                $this->error("❌ Falha na requisição com a chave {$poolManager->maskKey($selectedKey)}: HTTP {$statusCode}");
                $this->line("Resposta da API: {$body}");

                if ($statusCode === 429) {
                    $poolManager->markKeyCooldown($selectedKey, "Rate limit atingido no teste");
                }
            } catch (\Throwable $e) {
                $this->error("❌ Erro inesperado: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("==========================================================");
        $this->info("   🎉 TESTES FINALIZADOS COM SUCESSO!                     ");
        $this->info("==========================================================");

        return 0;
    }
}

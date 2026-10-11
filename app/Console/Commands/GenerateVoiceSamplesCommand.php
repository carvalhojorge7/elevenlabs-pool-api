<?php

namespace App\Console\Commands;

use App\Services\ElevenLabs\KeyPoolManager;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateVoiceSamplesCommand extends Command
{
    protected $signature = 'elevenlabs:samples 
                            {--model=eleven_v4_turbo : Modelo da ElevenLabs a utilizar}
                            {--limit=8 : Quantidade de vozes para gerar amostra}';

    protected $description = 'Gera amostras reais de áudio com as principais vozes premade para escolha do narrador.';

    // Amostra padronizada, rápida e com palavras desafiadoras
    protected string $sampleText = 'Olá! Esta é uma demonstração da minha voz em português. Palavra chave: Paralelepípedo.';

    public function handle(KeyPoolManager $poolManager): int
    {
        $this->info("==========================================================");
        $this->info("   🎙️ GERADOR DE AMOSTRAS DE VOZES (ELEVENLABS)           ");
        $this->info("==========================================================");

        $keys = $poolManager->getAvailableKeys();
        if (empty($keys)) {
            $this->error("Nenhuma chave válida configurada no pool!");
            return 1;
        }

        $apiKey = $keys[0];
        $model = $this->option('model');
        $limit = (int) $this->option('limit');

        $this->line("Usando modelo: <comment>{$model}</comment>");
        $this->newLine();

        $client = new Client([
            'base_uri' => 'https://api.elevenlabs.io/',
            'timeout' => 45,
        ]);

        $this->info("Buscando vozes disponíveis na conta...");
        $response = $client->get('v1/voices', [
            'headers' => ['xi-api-key' => $apiKey]
        ]);

        $allVoices = json_decode((string) $response->getBody(), true)['voices'] ?? [];

        // Filtra vozes premade (permitidas na API para contas gratuitas)
        $premadeVoices = array_filter($allVoices, fn($v) => ($v['category'] ?? '') === 'premade');

        // Seleção variada das vozes mais populares masculinas e femininas
        $curatedIds = [
            'JBFqnCBsd6RMkjVDRZzb', // George (Masculino maduro)
            'CwhRBWXzGAHq8TQ4Fs17', // Roger (Masculino casual)
            'pNInz6obpgDQGcFmaJgB', // Adam (Masculino firme)
            'IKne3meq5aSn9XLyUdCD', // Charlie (Masculino jovem/grave)
            'nPczCjzI2devNBz1zQrb', // Brian (Masculino profundo)
            'EXAVITQu4vr4xnSDxMaL', // Sarah (Feminina expressiva)
            'FGY2WhTYpPnrIDTdsKH5', // Laura (Feminina jovem)
            'Xb7hH8MSUJpSbSDYk0k2', // Alice (Feminina clara/educadora)
        ];

        $targetVoices = [];
        foreach ($curatedIds as $cid) {
            foreach ($premadeVoices as $v) {
                if ($v['voice_id'] === $cid) {
                    $targetVoices[] = $v;
                    break;
                }
            }
        }

        $targetVoices = array_slice($targetVoices, 0, $limit);

        $outputDir = storage_path('app/testing_audio/samples');
        File::ensureDirectoryExists($outputDir);

        $results = [];

        foreach ($targetVoices as $idx => $voice) {
            $voiceId = $voice['voice_id'];
            $name = $voice['name'];
            $gender = $voice['labels']['gender'] ?? 'N/A';

            $this->line("----------------------------------------------------------");
            $this->info("Gerando amostra ".($idx + 1)."/".count($targetVoices).": <fg=cyan>{$name}</> ({$gender})");
            $this->line("Voice ID: <comment>{$voiceId}</comment>");

            try {
                $ttsResponse = $client->post("v1/text-to-speech/{$voiceId}", [
                    'headers' => [
                        'xi-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'audio/mpeg',
                    ],
                    'json' => [
                        'text' => $this->sampleText,
                        'model_id' => $model,
                    ],
                ]);

                if ($ttsResponse->getStatusCode() === 200) {
                    $audioContent = (string) $ttsResponse->getBody();
                    $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', explode(' -', $name)[0]);
                    $filename = "amostra_{$gender}_{$cleanName}.mp3";
                    $filepath = "{$outputDir}/{$filename}";
                    file_put_contents($filepath, $audioContent);

                    $this->info("✅ Salvo em: {$filepath}");
                    $results[] = [
                        'Nome' => $name,
                        'Gênero' => $gender,
                        'Voice ID' => $voiceId,
                        'Arquivo' => $filename,
                    ];
                }
            } catch (\Throwable $e) {
                $this->error("❌ Falha: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("==========================================================");
        $this->info("   📊 TABELA COMPARATIVA DE VOZES GERADAS                 ");
        $this->info("==========================================================");
        $this->table(['Nome', 'Gênero', 'Voice ID', 'Arquivo'], $results);

        $this->info("Diretório das amostras: {$outputDir}");

        return 0;
    }
}

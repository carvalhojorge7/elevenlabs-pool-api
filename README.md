# 🎙️ ElevenLabs Pool API

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

Uma API robusta e leve desenvolvida em **Laravel 12 / PHP 8.3** que atua como **Proxy Reverso Inteligente** e espelho fiel da API oficial da [ElevenLabs](https://elevenlabs.io/).

O principal objetivo do projeto é gerenciar uma **Pool de Chaves de API**, realizando balanceamento contínuo (**Round-Robin**) e **Failover Automático** caso alguma chave esgote seus créditos ou atinja rate limit (HTTP 429), garantindo alta disponibilidade e transparência total para suas aplicações consumidoras.

---

## ✨ Principais Funcionalidades

- **🔄 Espelhamento Fiel da API ElevenLabs**:
  - Aceita requisições idênticas em `/v1/{endpoint}` (ou `/api/v1/{endpoint}`) repassando métodos HTTP (`GET`, `POST`, `DELETE`, etc.), cabeçalhos, parâmetros de query e payloads JSON ou multipart.
- **🏊 Gestão e Rotação de Pool de Chaves**:
  - Cadastro de múltiplas chaves separadas por vírgula no `.env`.
  - Rotação contínua Round-Robin distribuindo o consumo de caracteres entre as contas.
- **🛡️ Tolerância a Falhas e Failover Imediato**:
  - Se a chave da rodada retornar HTTP `429 (Too Many Requests / Cota Excedida)` ou erro de créditos, ela entra automaticamente em **cooldown temporário** (Cache/Redis) e a requisição é atendida na mesma hora pela próxima chave disponível.
- **🔊 Streaming de Áudio Binário Nativo**:
  - Suporte completo a transferência em streaming (`audio/mpeg` / Chunked Transfer) para endpoints de síntese de voz (TTS - `/v1/text-to-speech/{voice_id}`).
- **📊 Healthcheck e Monitoramento do Pool**:
  - Endpoint `/api/pool/status` que consulta a API da ElevenLabs e relata em tempo real o plano (Tier), total de caracteres consumidos, limite e saldo restante de cada chave da pool.
- **🐳 100% Dockerizado**:
  - Imagem otimizada baseada em Alpine Linux com porta configurável diretamente no `.env` (ex: `9133`).
- **🧪 Comandos Artisan de Teste e Amostras**:
  - `php artisan elevenlabs:test-pool`: Testa a obtenção de saldo de cada chave real, rotação e gera áudio sequencial autoincrementado com palavras complexas em português.
  - `php artisan elevenlabs:samples`: Sintetiza amostras das principais vozes da sua conta com o modelo configurado (ex: `eleven_v4_turbo`) para decisão de narrador.

---

## 🏗️ Arquitetura do Sistema

```
                             ┌───────────────────────────────────────────────┐
                             │          ElevenLabs Pool API                  │
                             │               (Laravel 12)                    │
                             │                                               │
   [Cliente / Frontend] ───► │  [ElevenLabsProxyController]                  │
  (GET/POST /v1/{endpoint})   │                 │                             │
                             │                 ▼                             │
                             │     [ElevenLabsProxyService]                  │
                             │                 ▲                             │
                             │                 │ (Pega próxima chave ativa)  │
                             │        [KeyPoolManager] ◄─── (Cache / SQLite) │
                             │          • Round-Robin                        │
                             │          • Cooldown em 429 / 401              │
                             └─────────┬─────────────────────────────────────┘
                                       │
                              (Injeta cabeçalho xi-api-key)
                                       │
                                       ▼
                       [API Oficial da ElevenLabs]
                     (https://api.elevenlabs.io/v1/...)
```

---

## 🚀 Como Executar

### Opção 1: Via Docker Compose (Recomendado)

1. Clone o repositório:
   ```bash
   git clone git@github.com:carvalhojorge7/elevenlabs-pool-api.git
   cd elevenlabs-pool-api
   ```

2. Crie seu arquivo `.env`:
   ```bash
   cp .env.example .env
   ```

3. Configure suas chaves, segurança e porta no `.env`:
   ```env
   # Porta onde a API será exposta (Docker ou Local)
   APP_PORT=9133
   APP_URL=http://localhost:9133

   # CHAVE MASTER DE PROTEÇÃO DO PROXY (Obrigatória para os clientes autenticarem nas suas rotas)
   # Os clientes devem enviar esta chave no cabeçalho 'xi-api-key' ou 'Authorization: Bearer <chave>'
   ELEVENLABS_MASTER_KEY=sua_chave_master_secreta_aqui

   # POOL DE CHAVES REAIS DA ELEVENLABS (Separadas por vírgula, sem espaços)
   # O proxy fará rotação contínua (Round-Robin) e failover automático entre elas
   ELEVENLABS_API_KEYS=sk_chave1_aqui,sk_chave2_aqui,sk_chave3_aqui

   # Narrador padrão e modelo desejado
   ELEVENLABS_DEFAULT_VOICE_ID=CwhRBWXzGAHq8TQ4Fs17
   ELEVENLABS_DEFAULT_MODEL_ID=eleven_v4_turbo

   # Tempo em minutos que uma chave fica suspensa ao atingir limite (429/401)
   ELEVENLABS_COOLDOWN_MINUTES=30
   ELEVENLABS_REQUEST_TIMEOUT=60
   ```

4. Suba o container:
   ```bash
   docker compose up -d --build
   ```

A API estará disponível imediatamente em: `http://localhost:9133`

---

### Opção 2: Localmente (Sem Docker)

Requisitos: **PHP >= 8.2**, **Composer 2.x** e extensão **SQLite**.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=9133
```

---

## 📡 Exemplos de Uso

Todas as requisições direcionadas ao proxy devem incluir a sua **Chave Master** configurada em `ELEVENLABS_MASTER_KEY` via cabeçalho `xi-api-key`, `X-Api-Key` ou `Authorization: Bearer <master_key>`.

### 1. Consultar Saúde e Saldo de Cada Chave da Pool
```bash
curl http://localhost:9133/api/pool/status \
  -H "xi-api-key: SUA_CHAVE_MASTER_AQUI"
```

**Exemplo de Resposta:**
```json
{
  "total_keys": 2,
  "available_keys_count": 2,
  "keys": [
    {
      "key_masked": "sk_4...5bce",
      "status": "active",
      "requests_count": 12,
      "tier": "free",
      "character_count": 3995,
      "character_limit": 10000,
      "remaining_characters": 6005
    },
    {
      "key_masked": "sk_8...9fa1",
      "status": "active",
      "requests_count": 11,
      "tier": "starter",
      "character_count": 12400,
      "character_limit": 30000,
      "remaining_characters": 17600
    }
  ]
}
```

---

### 2. Sintetizar Áudio (TTS) via Proxy
Faça a mesma chamada que faria para `https://api.elevenlabs.io`, mantendo o mesmo cabeçalho `xi-api-key` com a sua Chave Master:

```bash
curl -X POST "http://localhost:9133/v1/text-to-speech/CwhRBWXzGAHq8TQ4Fs17" \
  -H "xi-api-key: SUA_CHAVE_MASTER_AQUI" \
  -H "Content-Type: application/json" \
  -H "Accept: audio/mpeg" \
  -d '{
    "text": "Olá! Esta é uma requisição passando pela nossa pool de chaves da ElevenLabs.",
    "model_id": "eleven_v4_turbo"
  }' \
  --output narracao.mp3
```

---

### 3. Listar Vozes Disponíveis
```bash
curl http://localhost:9133/v1/voices \
  -H "xi-api-key: SUA_CHAVE_MASTER_AQUI"
```

---

## 🧪 Comandos Utilitários via CLI

Você pode executar os comandos Artisan dentro do container ou na máquina local:

- **Testar saldo, rotação e gerar áudio de teste com palavras difíceis em PT-BR:**
  ```bash
  docker compose exec elevenlabs-api php artisan elevenlabs:test-pool --iterations=2
  ```

- **Gerar amostras de vozes disponíveis na conta:**
  ```bash
  docker compose exec elevenlabs-api php artisan elevenlabs:samples --model=eleven_v4_turbo
  ```

---

## 🔒 Segurança

- O repositório **nunca** armazena credenciais ou chaves reais (`.env` está no `.gitignore`).
- As chaves de API têm sua visualização mascarada nos endpoints públicos e logs (ex: `sk_4...5bce`).
- Os arquivos de áudio temporários gerados em testes locais também são ignorados pelo Git.

---

## 📄 Licença

Distribuído sob a licença [MIT](LICENSE).

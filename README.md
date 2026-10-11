# 🎙️ ElevenLabs Pool API (Laravel)

Uma API desenvolvida em **Laravel** que atua como proxy reverso transparente e espelho fiel da API oficial da [ElevenLabs](https://elevenlabs.io/), com balanceamento de carga, rotação inteligente de chaves de API (Round-Robin) e tolerância automática a falhas (Failover / Cooldown em caso de cota excedida - HTTP 429).

---

## 🚀 Funcionalidades

- **Espelhamento Transparente:** Aceita as mesmas requisições (métodos HTTP, query strings, headers e payloads JSON/multipart) para qualquer rota `/v1/{path}` e repassa para a ElevenLabs.
- **Pool de Chaves Inteligente:** Rotação contínua (Round-Robin) entre as chaves cadastradas no `.env`.
- **Failover Automático:** Se uma chave atingir o limite de requisições ou créditos (HTTP 429), ela entra em cooldown temporário via cache e a requisição é automaticamente atendida pela próxima chave disponível.
- **Streaming de Áudio Binário:** Suporte nativo a áudio `audio/mpeg` para endpoints de Text-to-Speech (TTS).
- **Monitoramento do Pool:** Endpoint `/api/pool/status` informando o saldo de caracteres restantes, uso e estado de cada chave.
- **Comando de Teste Real:** Teste automatizado (`php artisan elevenlabs:test-pool`) para validação de saldo e síntese de áudio com contagem autoincrementada e palavras complexas em português.

---

## 🛠️ Requisitos

- PHP >= 8.2 (testado em PHP 8.3)
- Composer 2.x
- Extensões PHP padrão do Laravel (cURL, OpenSSL, SQLite/PDO)

---

## ⚙️ Instalação e Configuração

1. Clone o repositório ou navegue até o diretório:
   ```bash
   cd "elevenlabs-pool-api"
   ```

2. Instale as dependências:
   ```bash
   composer install
   ```

3. Configure as variáveis de ambiente no `.env`:
   ```env
   # Lista de chaves reais da ElevenLabs separadas por vírgula
   ELEVENLABS_API_KEYS=chave_1,chave_2,chave_3

   # ID da voz padrão (ex: eUAnqvLQWNX29twcYLUM)
   ELEVENLABS_DEFAULT_VOICE_ID=eUAnqvLQWNX29twcYLUM

   # Modelo multilíngue (recomendado para português natural)
   ELEVENLABS_DEFAULT_MODEL_ID=eleven_multilingual_v2

   # Tempo em minutos para uma chave com erro 429 ficar suspensa
   ELEVENLABS_COOLDOWN_MINUTES=30
   ```

4. Execute as migrations para o banco SQLite local (utilizado para cache e sessões):
   ```bash
   php artisan migrate
   ```

---

## 🧪 Teste Real com as Chaves (Sem Fakes)

Para validar a integridade das suas chaves reais, verificar o saldo de caracteres de cada uma e testar a rotação gravando os áudios em disco:

```bash
php artisan elevenlabs:test-pool --iterations=2
```

O comando irá:
1. Consultar a assinatura de cada chave (`/v1/user/subscription`) e imprimir uma tabela com os créditos restantes.
2. Executar chamadas alternadas gerando áudios no padrão:
   > *"Este é o teste número 1 e a palavra chave é: Otorrinolaringologista."*  
   > *"Este é o teste número 2 e a palavra chave é: Inconstitucionalissimamente."*
3. Salvar os arquivos `.mp3` em `storage/app/testing_audio/`.
4. Mostrar no console qual chave da pool atendeu cada requisição.

---

## 🌐 Endpoints da API

Inicie o servidor de desenvolvimento:
```bash
php artisan serve
```

### 1. Monitoramento do Pool
```http
GET /api/pool/status
```
Retorna um JSON detalhado com o total de chaves ativas, saldo em caracteres de cada uma e histórico de requisições.

### 2. Sintetizar Áudio (TTS) via Proxy
Exatamente como na API oficial da ElevenLabs, utilizando o endpoint local:
```bash
curl -X POST "http://localhost:8000/v1/text-to-speech/eUAnqvLQWNX29twcYLUM" \
  -H "Content-Type: application/json" \
  -H "Accept: audio/mpeg" \
  -d '{
    "text": "Olá! Esta é uma demonstração da API de pool de chaves em Laravel.",
    "model_id": "eleven_multilingual_v2"
  }' \
  --output narracao.mp3
```

### 3. Listar Vozes Disponíveis
```bash
curl -X GET "http://localhost:8000/v1/voices"
```

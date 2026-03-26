# Capitulo 2 — Setup do Ambiente

> **Abordagem Docker-first:** Neste tutorial, **tudo roda dentro de containers**. Voce nao precisa ter PHP, Composer ou Node instalados na sua maquina — apenas Docker.

## Pre-requisitos

- **Docker** e **Docker Compose** instalados ([docs.docker.com/get-docker](https://docs.docker.com/get-docker/))
- **Git**
- **GitHub CLI (`gh`)** — opcional, mas facilita ([cli.github.com](https://cli.github.com/))
- Uma **chave de API do Google Gemini** — gratuita em [aistudio.google.com](https://aistudio.google.com/apikey)
- Portas 80, 5432 livres no host

> **Importante:** Nao e necessario ter PHP, Composer ou Node.js instalados localmente. Todo o ambiente roda via Docker.

---

## Passo 0 — Criar o repositorio e versionar desde o inicio

Antes de qualquer codigo, vamos criar a estrutura do projeto e o repositorio Git. Versionar desde o primeiro momento e uma pratica essencial — cada capitulo do tutorial sera um commit (ou mais), criando um historico limpo do projeto.

```bash
# Criar diretorio raiz do projeto
mkdir laravel_ai
cd laravel_ai

# Inicializar o repositorio Git
git init

# Criar .gitignore raiz (ignora arquivos do sistema)
cat > .gitignore << 'EOF'
.DS_Store
Thumbs.db
*.swp
*.swo
.idea/
.vscode/
EOF

# Criar estrutura de documentacao
mkdir docs

# Primeiro commit — repositorio vazio com estrutura
git add .
git commit -m "chore: init project structure"
```

### Criar repositorio no GitHub

```bash
# Com GitHub CLI (recomendado)
gh repo create codereview-ai --public --source=. --push

# Ou manualmente:
# 1. Crie o repo em github.com/new
# 2. Conecte:
git remote add origin git@github.com:SEU-USUARIO/codereview-ai.git
git branch -M main
git push -u origin main
```

### Proteger a branch main

Em projetos profissionais, **nunca se faz push direto na `main`**. Vamos configurar o GitHub para obrigar o uso de branches e Pull Requests:

1. Acesse o repositorio no GitHub
2. Va em **Settings > Rules > Rulesets > New branch ruleset**
3. Configure:
   - **Ruleset name:** `main-protection`
   - **Enforcement status:** `Active` (importante! Se ficar `Disabled` a regra nao funciona)
   - **Target branches:** Clique em "Add target" e selecione `Default` (main)
4. Em **Branch rules**, marque:
   - [x] **Restrict deletions** — impede deletar a branch main
   - [x] **Require a pull request before merging** — obriga criar PR
     - Required approvals: `0` (ok para projeto solo, em equipe coloque 1+)
   - [x] **Block force pushes** — impede `git push --force` na main
5. Em **Allowed merge methods**, deixe: `Merge, Squash, Rebase`
6. Clique em **Create**

> **Nota:** O **Require status checks to pass** sera ativado no Capitulo 14 quando configurarmos o GitHub Actions. Por enquanto deixe desmarcado.

> **Por que?** Isso garante que todo codigo passe por review (mesmo que seja seu proprio review) e que os testes passem antes de entrar na `main`. E uma pratica padrao da industria. Com essa configuracao, qualquer `git push origin main` sera rejeitado — voce **precisa** criar uma branch, fazer push dela e abrir um PR.

### Fluxo de trabalho com branches

A partir de agora, **todo trabalho sera feito em branches**. O fluxo para cada capitulo:

```
main (protegida — nao aceita push direto)
  |
  +-- git checkout -b feat/cap02-setup
  |     |
  |     +-- commits do capitulo 2
  |     |
  |     +-- git push -u origin feat/cap02-setup
  |     |
  |     +-- Criar PR no GitHub (ou via gh pr create)
  |     |
  |     +-- Review + Merge na main
  |
  +-- git checkout -b feat/cap03-database
  |     |
  |     +-- commits do capitulo 3
  |     ...
```

```bash
# Exemplo: criar branch para o Capitulo 2
git checkout -b feat/cap02-setup

# ... fazer as alteracoes do capitulo ...

# Commitar
git add .
git commit -m "feat: setup ambiente com docker, pgvector e laravel/ai"

# Push da branch
git push -u origin feat/cap02-setup

# Criar Pull Request via GitHub CLI
gh pr create --title "feat: setup do ambiente" --body "Capitulo 02 - Docker, pgvector, .env e dependencias"

# Apos review, fazer merge via GitHub (botao na interface)
# Ou via CLI:
gh pr merge --squash

# Voltar para main atualizada
git checkout main
git pull

# Criar branch para o proximo capitulo
git checkout -b feat/cap03-database
```

### Convencao de nomes

| Tipo | Padrao da branch | Exemplo |
|------|-----------------|---------|
| Feature | `feat/cap{NN}-descricao` | `feat/cap02-setup` |
| Bug fix | `fix/descricao` | `fix/pgvector-extension` |
| Infra | `chore/descricao` | `chore/github-actions` |
| Docs | `docs/descricao` | `docs/readme-update` |

> **Dica:** Use [Conventional Commits](https://www.conventionalcommits.org/) nas mensagens — `feat:` para features, `fix:` para correcoes, `chore:` para infraestrutura, `docs:` para documentacao. Isso facilita gerar changelogs e entender o historico.

### Resumo dos commits por capitulo

```
feat/cap02-setup:
  feat: create laravel project with sail (pgsql + redis)
  feat: configure pgvector in compose.yaml and init script
  feat: configure .env for pgsql, redis and gemini
  feat: install laravel/ai, pgvector, livewire and pest

feat/cap03-database:
  feat: add database migrations and pgvector setup

feat/cap04-models:
  feat: add eloquent models, enums and relationships

feat/cap05-routes:
  feat: add routes and livewire volt components

...e assim por diante
```

---

## Passo 1 — Criar o projeto Laravel via Docker

Como nao temos PHP/Composer no host, usamos o **Laravel Build** (script oficial) que cria o projeto inteiro dentro de um container temporario:

```bash
# Dentro do diretorio laravel_ai/
# Opcao A: Laravel Build (recomendado)
# Cria projeto + instala Sail com PostgreSQL e Redis
curl -s "https://laravel.build/codereview-ai?with=pgsql,redis" | bash
```

Este comando faz tudo dentro de um container Docker:
1. Puxa a imagem `laravelsail/php85-composer`
2. Roda `composer create-project laravel/laravel` dentro do container
3. Instala Laravel Sail automaticamente
4. Configura `compose.yaml` com PostgreSQL e Redis
5. O codigo fica no diretorio `codereview-ai/` no seu host (via volume mount)

```bash
cd codereview-ai
```

> **Estrutura de diretorios apos este passo:**
> ```
> laravel_ai/               <- repositorio Git (raiz)
>   .git/
>   .gitignore
>   docs/                   <- tutoriais .md
>   codereview-ai/          <- projeto Laravel (criado pelo Sail)
>     app/
>     compose.yaml
>     vendor/
>     ...
> ```

```bash
# Commitar o projeto Laravel recem-criado (na branch feat/cap02-setup)
cd ..  # volta para laravel_ai/
git add .
git commit -m "feat: create laravel project with sail (pgsql + redis)"
```

> **Opcao B (manual):** Se o `laravel.build` nao funcionar na sua rede, voce pode criar manualmente:
>
> ```bash
> mkdir codereview-ai && cd codereview-ai
>
> # Usa container temporario para rodar composer
> docker run --rm \
>     -u "$(id -u):$(id -g)" \
>     -v "$(pwd):/var/www/html" \
>     -w /var/www/html \
>     laravelsail/php85-composer:latest \
>     bash -c "composer create-project laravel/laravel . && php artisan sail:install --with=pgsql,redis"
> ```

### O que aconteceu?

```
Host (sua maquina)               Container Docker (temporario)
+-----------------+              +-----------------------------+
|                  |   volume    | laravelsail/php85-composer  |
| codereview-ai/  |<----------->|                             |
|   (diretorio)   |   mount     | composer create-project     |
|                  |             | laravel/laravel .           |
+-----------------+              | php artisan sail:install    |
                                 +-----------------------------+
                                        | container destruido
                                 Codigo permanece no host
```

---

## Passo 2 — Configurar o PostgreSQL com pgvector

O Sail gera um `compose.yaml` padrao com PostgreSQL. Precisamos trocar a imagem para incluir pgvector.

Abra o `compose.yaml` e altere o servico `pgsql`:

```yaml
pgsql:
    image: 'pgvector/pgvector:pg18'   # <- Troca postgres:18-alpine por pgvector
    ports:
        - '${FORWARD_DB_PORT:-5432}:5432'
    environment:
        PGPASSWORD: '${DB_PASSWORD:-password}'
        POSTGRES_DB: '${DB_DATABASE}'
        POSTGRES_USER: '${DB_USERNAME}'
        POSTGRES_PASSWORD: '${DB_PASSWORD:-password}'
    volumes:
        - 'sail-pgsql:/var/lib/postgresql'
        - './docker/pgsql/create-testing-database.sql:/docker-entrypoint-initdb.d/10-create-testing-database.sql'
    networks:
        - sail
    healthcheck:
        test:
            - CMD
            - pg_isready
            - '-q'
            - '-d'
            - '${DB_DATABASE}'
            - '-U'
            - '${DB_USERNAME}'
        retries: 3
        timeout: 5s
```

### Por que `pgvector/pgvector:pg18`?

| Imagem | PostgreSQL | pgvector | RAG |
|--------|-----------|---------|-----|
| `postgres:18-alpine` | sim | Precisa instalar manualmente | nao |
| `pgvector/pgvector:pg18` | sim | Ja incluso | Pronto para RAG |

A imagem `pgvector/pgvector:pg18` e o PostgreSQL 18 oficial **com a extensao pgvector pre-instalada**. Sem ela, nao conseguimos usar busca vetorial (essencial para RAG no Capitulo 9).

### Script de inicializacao do banco

Crie o diretorio e arquivo para auto-configurar pgvector no boot do container:

```bash
mkdir -p docker/pgsql
```

Crie `docker/pgsql/create-testing-database.sql`:

```sql
SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec

-- Habilitar pgvector no banco principal e testing
\c laravel
CREATE EXTENSION IF NOT EXISTS vector;

\c testing
CREATE EXTENSION IF NOT EXISTS vector;
```

> **O que faz:** Toda vez que o container PostgreSQL inicia pela primeira vez, ele executa scripts em `/docker-entrypoint-initdb.d/`. Nosso script cria o banco de testes e habilita a extensao `vector` em ambos os bancos automaticamente.

```bash
# Commitar configuracao do pgvector
cd ..  # volta para laravel_ai/
git add .
git commit -m "feat: configure pgvector in compose.yaml and init script"
```

---

## Passo 3 — Configurar o .env

```bash
cp .env.example .env
```

Edite o `.env` com as configuracoes essenciais:

```env
APP_NAME="CodeReview AI"
APP_ENV=local
APP_URL=http://localhost

# Banco de dados — host e 'pgsql' (nome do servico no compose.yaml)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Redis — host e 'redis' (nome do servico no compose.yaml)
REDIS_HOST=redis
CACHE_STORE=redis

# Queue — usa database driver para jobs de IA assincronos
QUEUE_CONNECTION=database

# ============================================
# AI Engineering — Google Gemini (Free Tier)
# ============================================
# Gere sua chave GRATIS em: https://aistudio.google.com/apikey
AI_PROVIDER=gemini
GEMINI_API_KEY=AIza-sua-chave-aqui

# Modelos Gemini utilizados no projeto
GEMINI_MODEL=gemini-2.5-flash
GEMINI_MODEL_LITE=gemini-2.5-flash-lite
GEMINI_EMBEDDING_MODEL=text-embedding-004
```

### Por que `DB_HOST=pgsql` e nao `localhost`?

```
+---------------------------------------------------+
| Docker Network (sail)                             |
|                                                   |
|  +--------------+      +--------------+           |
|  | laravel.test |----->|    pgsql     |           |
|  |  (PHP app)   |      | (PostgreSQL) |           |
|  |              |      |              |           |
|  | DB_HOST=pgsql|      | porta 5432   |           |
|  +--------------+      +--------------+           |
|         |                                         |
|         |              +--------------+           |
|         +------------->|    redis     |           |
|                        |              |           |
|       REDIS_HOST=redis | porta 6379   |           |
|                        +--------------+           |
+---------------------------------------------------+
```

Dentro da rede Docker, cada servico e acessivel pelo **nome do servico** definido no `compose.yaml`. O container PHP acessa o PostgreSQL via `pgsql:5432`, nao `localhost:5432`.

> **Nota:** O `.env` contem sua `GEMINI_API_KEY`. Nunca commite chaves de API. O `.gitignore` do Laravel ja ignora o `.env`, mas sempre confira.

```bash
# Commitar configuracao do ambiente (sem o .env — ja esta no .gitignore)
cd ..  # volta para laravel_ai/
git add .
git commit -m "feat: configure .env for pgsql, redis and gemini"
```

---

## Passo 4 — Subir os containers

Agora sim, subimos o ambiente Docker:

```bash
./vendor/bin/sail up -d
```

Este comando:
1. Builda a imagem PHP 8.5 a partir do Dockerfile do Sail
2. Puxa as imagens `pgvector/pgvector:pg18` e `redis:alpine`
3. Cria a rede Docker `sail`
4. Inicia os 3 containers em background (`-d`)

### Verificar se esta tudo rodando

```bash
./vendor/bin/sail ps
```

Deve mostrar 3 containers: `laravel.test`, `pgsql`, `redis` — todos com status `Up`.

> **Dica:** Crie um alias para nao ter que digitar `./vendor/bin/sail` toda vez:
> ```bash
> # Adicionar ao ~/.bashrc (ou ~/.zshrc) para ficar permanente
> echo "alias sail='./vendor/bin/sail'" >> ~/.bashrc
> source ~/.bashrc
> ```
> A partir daqui, **todos os capitulos** usam `sail` como alias. Se um comando `sail` retornar "command not found", rode o alias acima ou use `./vendor/bin/sail` no lugar.

---

## Passo 5 — Instalar dependencias (dentro do container)

Agora que o ambiente esta rodando, **todos os comandos sao executados dentro do container** via Sail:

```bash
# Gerar chave da aplicacao
sail artisan key:generate

# Instalar Laravel AI SDK (inclui Prism PHP como dependencia)
sail composer require laravel/ai

# Publicar config e migrations do AI SDK
sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"

# Instalar pgvector PHP client (para busca vetorial)
sail composer require pgvector/pgvector

# Instalar Livewire 4.2 (UI reativa)
sail composer require livewire/livewire

# Instalar dependencias de dev
sail composer require --dev pestphp/pest laravel/pail

# Rodar as migrations (cria todas as tabelas)
sail artisan migrate

# Instalar dependencias JS e compilar assets
sail npm install
sail npm run dev
```

```bash
# Commitar dependencias e migrations do AI SDK
cd ..  # volta para laravel_ai/
git add .
git commit -m "feat: install laravel/ai, pgvector, livewire and pest"

# Push da branch e criar PR
git push -u origin feat/cap02-setup
gh pr create --title "feat: setup do ambiente" --body "Capitulo 02 - Docker, pgvector, .env, Laravel AI SDK e dependencias"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

### O que cada comando faz por tras

```
Sua maquina (host)                    Container Docker (laravel.test)
+------------------+                 +-----------------------------+
|                  |                 | PHP 8.5 + Composer + Node   |
| Terminal:        |    sail exec    |                             |
| sail composer    |---------------->| composer require laravel/ai |
| require laravel/ai                 |                             |
|                  |<---volume------>| vendor/ atualizado          |
| vendor/ visivel  |    mount        |                             |
| no host tambem   |                 |                             |
+------------------+                 +-----------------------------+
```

`sail composer` = `docker exec -it laravel.test composer` — executa Composer **dentro** do container, mas como o diretorio e um volume mount, os arquivos ficam sincronizados com o host.

---

## Laravel AI SDK — Configuracao

Apos `vendor:publish`, o arquivo `config/ai.php` e criado:

```php
// config/ai.php (publicado pelo Laravel AI SDK)
return [
    'default' => env('AI_PROVIDER', 'gemini'),
    'providers' => [
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],
        // + groq, mistral, deepseek, ollama, etc.
    ],
];
```

### Providers suportados

| Provider | Modelos | Custo | Ideal para |
|----------|---------|-------|-----------|
| **Google Gemini** (padrao) | gemini-2.5-flash, flash-lite | **Gratis** (250-1000 req/dia) | Tutorial, prototipos |
| OpenAI | gpt-4o, gpt-4o-mini | Pre-pago ($5 minimo) | Producao high-volume |
| Anthropic | claude-sonnet-4, claude-haiku | Pre-pago | Analise complexa |
| Ollama | llama3, codellama | Gratis (local) | Offline, privacidade |
| Groq | llama-3.3-70b | Gratis (rate limit) | Fast inference |

Para trocar de provider, basta alterar `AI_PROVIDER` e a API key no `.env`.

---

## Dependencias de AI Engineering

### 1. Laravel AI SDK (`laravel/ai`)

```bash
sail composer require laravel/ai
```

**O que e:** Toolkit oficial do Laravel para IA — Agents, Tools, Structured Output, Embeddings.
**Internamente:** Usa Prism PHP como base.
**Providers:** Gemini, OpenAI, Anthropic, Ollama, Groq, Mistral, xAI, DeepSeek e mais.

### 2. pgvector/pgvector (v0.2.2+)

```bash
sail composer require pgvector/pgvector
```

**O que e:** Cliente PHP para a extensao pgvector do PostgreSQL.
**Por que:** RAG precisa de busca vetorial (embeddings) em producao.
**Capitulo:** 09 (RAG com pgvector).

### 3. Livewire (v4.2+)

```bash
sail composer require livewire/livewire
```

**O que e:** Framework reativo, componentes single-file (Volt).
**Por que:** UI interativa para Kanban, forms, real-time updates.
**Capitulo:** 05-06.

---

## Pilares de IA Engineering na Stack

Cada pilar usa dependencia especifica:

| Pilar de IA | Tecnologia | Capitulo |
|------------|-----------|----------|
| **Prompt Engineering** | Laravel AI SDK (Agent `instructions()`) | 8 |
| **Structured Output** | Laravel AI SDK (`HasStructuredOutput`) | 8 |
| **RAG** | `Ai::embeddings()` + pgvector | 9 |
| **Multi-Agents** | Agent classes (`make:agent`) | 10 |
| **Tool Use** | `HasTools` + Tool classes (`make:tool`) | 10 |
| **Vector Database** | pgvector + PostgreSQL 18 | 3, 9 |
| **Orchestration** | Agent chama Agents via Tools | 10 |
| **AI Infrastructure** | Queue, Workers, FakeAi, Events | 11, 12 |

---

## Passo 6 — Verificar a instalacao

### Aplicacao

Acesse `http://localhost` no navegador. Voce deve ver a pagina padrao do Laravel.

### pgvector

```bash
# Acessar o console do PostgreSQL
sail exec pgsql psql -U sail -d laravel
```

> **Nota:** `sail pgsql` nao e um comando valido do Sail. Use `sail exec pgsql psql -U sail -d laravel` para acessar o console do PostgreSQL.

No console do PostgreSQL:

```sql
-- Verificar se pgvector esta habilitado
\dx

-- Deve mostrar:
--  Name   | Version | Schema |       Description
-- --------+---------+--------+-------------------------
--  vector | 0.8.0   | public | vector data type and ivfflat and hnsw access methods
```

Se nao aparecer (caso o init script nao tenha rodado), crie manualmente:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### Laravel AI SDK

```bash
sail artisan tinker
```

```php
// Testar se o SDK esta configurado
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Enums\Lab;

$response = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed('Hello World');

echo count($response[0]->embedding); // 768
```

### Fila (Queue Worker)

Para que os jobs de IA funcionem, o worker de fila precisa estar rodando:

```bash
# Em um terminal separado
sail artisan queue:work --tries=3
```

Mantenha este comando rodando enquanto desenvolve. No Capitulo 12 (Deploy) configuraremos o Supervisor para gerenciar isso automaticamente.

---

## Comandos Sail mais usados

| Comando | Descricao |
|---------|-----------|
| `sail up -d` | Subir containers em background |
| `sail down` | Parar containers |
| `sail ps` | Ver status dos containers |
| `sail artisan migrate` | Rodar migrations |
| `sail artisan migrate:fresh --seed` | Recriar banco do zero + seeders |
| `sail artisan queue:work` | Processar jobs da fila |
| `sail artisan make:agent NomeAgent` | Criar Agent class (AI SDK) |
| `sail artisan make:tool NomeTool` | Criar Tool class (AI SDK) |
| `sail composer require pacote` | Instalar pacote PHP |
| `sail npm run dev` | Compilar assets (dev) |
| `sail npm run build` | Compilar assets (prod) |
| `sail tinker` | Console interativo Laravel |
| `sail test` | Rodar testes (Pest) |
| `sail exec pgsql psql -U sail -d laravel` | Acessar console do PostgreSQL |
| `sail shell` | Acessar shell do container |

> **Regra de ouro:** Se o comando precisa de PHP, Composer, Node ou artisan, rode via `sail`. Nunca rode direto no host.

---

## Estrutura Docker para producao (preview)

O projeto incluira um `Dockerfile` multi-stage otimizado para producao:

```
Stage 1: node:22-alpine     -> Compila assets (npm run build)
Stage 2: php:8.5-fpm-alpine -> Instala extensoes PHP + Composer
         |
         Nginx + Supervisor rodando PHP-FPM + Queue Worker
```

Veremos isso em detalhes no [Capitulo 12 — Deploy com Docker](12-deploy-docker.md).

---

## Troubleshooting

### Porta 80 em uso

```bash
# Altere APP_PORT no .env
APP_PORT=8080
sail up -d
# Acesse http://localhost:8080
```

### Permissoes de arquivos

```bash
sail artisan storage:link
sudo chown -R $USER:$USER storage bootstrap/cache
```

### Sail nao encontrado apos Opcao B

Se usou a opcao manual e `vendor/bin/sail` nao existe:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

### Container pgsql nao sobe (volume corrompido)

```bash
sail down -v          # Remove containers E volumes
sail up -d            # Recria tudo limpo
sail artisan migrate  # Recria tabelas
```

### Laravel 13 usa SQLite por padrao no .env

O Laravel 13 gera o `.env` com `DB_CONNECTION=sqlite`. Se voce rodar `sail artisan migrate` sem alterar, vai receber:

```
SQLSTATE[HY000]: General error: 1 near "EXTENSION": syntax error
(Connection: sqlite, SQL: CREATE EXTENSION IF NOT EXISTS vector)
```

**Solucao:** Certifique-se de ter configurado o `.env` conforme o Passo 3 deste capitulo, com `DB_CONNECTION=pgsql`.

### APP_NAME com espacos precisa de aspas

Se o `.env` tiver `APP_NAME=CodeReview AI` (sem aspas), o Sail retorna:

```
./.env: line 1: AI: command not found
Failed to parse dotenv file. Encountered unexpected whitespace at [CodeReview AI].
```

**Solucao:** Use aspas: `APP_NAME="CodeReview AI"`

### "Extension vector does not exist"

O script `docker/pgsql/create-testing-database.sql` so roda na **primeira inicializacao** do volume. Se o volume ja existia antes de adicionar o script:

```bash
# Opcao 1: Recriar volume
sail down -v && sail up -d

# Opcao 2: Criar extensao manualmente
sail exec pgsql psql -U sail -d laravel
CREATE EXTENSION IF NOT EXISTS vector;
```

---

## Resumo do Fluxo Docker-First

```
1. curl laravel.build    --> Container temporario cria projeto
2. Editar compose.yaml   --> Trocar postgres:18-alpine por pgvector/pgvector:pg18
3. Criar docker/pgsql/   --> Script auto-habilita pgvector
4. Configurar .env       --> DB_HOST=pgsql, GEMINI_API_KEY
5. sail up -d            --> Sobe 3 containers (app, pgsql, redis)
6. sail composer require --> Instala laravel/ai + pgvector DENTRO do container
7. sail artisan migrate  --> Cria tabelas DENTRO do container
8. sail npm run dev      --> Compila assets DENTRO do container
9. http://localhost       --> Acessa no browser do host
```

**Nenhum comando PHP, Composer ou Node roda direto no host.** Tudo passa pelo Sail (Docker).

## Proximo capitulo

No [Capitulo 3 — Banco de Dados e Migrations](03-banco-de-dados.md) vamos criar o schema completo e configurar o pgvector.

# Capitulo 12 — Deploy com Docker

> **Este capitulo cobre o pilar: AI Infrastructure (8)**

Neste capitulo vamos preparar o projeto para **producao** com Docker. Ao final, voce tera um Dockerfile multi-stage otimizado, Nginx, Supervisor, Docker Compose de producao, rota de health check e um checklist completo de deploy.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap12-deploy
cd codereview-ai
```

---

## Por que multi-stage?

O Dockerfile usa **2 stages** para reduzir o tamanho da imagem final:

```
Stage 1 (node:22-alpine):     Stage 2 (php:8.5-fpm-alpine):
+- Node.js 22                 +- PHP 8.5-FPM
+- npm                        +- Nginx
+- Vite                       +- Supervisor
+- Tailwind CSS               +- Extensoes PHP
+- Compila assets             +- Composer deps (laravel/ai, pgvector)
    |                          +- Codigo PHP
    public/build/ ----------->+- public/build/ (copiado)
                               +- Imagem final ~150MB

Sem multi-stage: ~800MB (Node + PHP + tudo)
Com multi-stage: ~150MB (so PHP + assets compilados)
```

---

## Passo 1 — Criar a configuracao do Nginx

Primeiro, crie o diretorio para os arquivos Docker:

```bash
mkdir -p docker/nginx
```

Crie `docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache de assets estaticos
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**O que cada bloco faz:**
- `try_files` — redireciona tudo para `index.php` (SPA-friendly)
- `fastcgi_pass 127.0.0.1:9000` — conecta ao PHP-FPM rodando no mesmo container
- `fastcgi_hide_header X-Powered-By` — remove header que expoe versao do PHP
- Cache de assets — `expires 1y` com `immutable` para arquivos estaticos do Vite

---

## Passo 2 — Criar a configuracao do Supervisor

```bash
mkdir -p docker/supervisor
```

Crie `docker/supervisor/supervisord.conf`:

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

O Supervisor gerencia **3 processos** dentro do mesmo container:

| Processo | Descricao |
|----------|-----------|
| **PHP-FPM** | Processa requests PHP (20 workers estaticos) |
| **Nginx** | Proxy reverso e arquivos estaticos |
| **Queue Worker** | 2 workers para processar jobs dos Agents de IA |

```bash
# Commitar configs Docker
cd ~/laravel_ai
git add .
git commit -m "feat: add nginx and supervisor configs for production"
```

---

## Passo 3 — Criar o Dockerfile multi-stage

Crie `Dockerfile` na raiz do projeto (`codereview-ai/Dockerfile`):

```dockerfile
# ============================================
# STAGE 1: Build dos assets (Node.js)
# ============================================
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copiar apenas package files primeiro (cache de camadas)
COPY package.json package-lock.json ./
RUN npm ci --production=false

# Copiar codigo e compilar
COPY . .
RUN npm run build

# ============================================
# STAGE 2: Aplicacao PHP
# ============================================
FROM php:8.5-fpm-alpine

# Instalar extensoes PHP necessarias
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        opcache \
        pcntl

# Configurar OPcache para producao
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM static pool para performance
RUN sed -i 's/pm = dynamic/pm = static/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_children = 5/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependencias PHP (sem dev)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Copiar codigo da aplicacao
COPY . .

# Copiar assets compilados do stage 1
COPY --from=node-builder /app/public/build public/build

# Otimizacoes Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Configurar Nginx e Supervisor
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Permissoes
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

**Pontos importantes:**
- `COPY package.json package-lock.json ./` primeiro — aproveita cache de camadas do Docker
- `composer install --no-dev` — nao instala dependencias de desenvolvimento em producao
- `validate_timestamps=0` — OPcache nao verifica se arquivos mudaram (mais rapido em producao)
- `pm = static` com `max_children = 20` — pool fixo de workers PHP-FPM para performance previsivel

```bash
# Commitar Dockerfile
cd ~/laravel_ai
git add .
git commit -m "feat: add multi-stage Dockerfile for production"
```

---

## Passo 4 — Criar a rota de health check

A rota de health check permite que load balancers e orquestradores (ECS, Kubernetes) verifiquem se a aplicacao esta funcionando.

Edite `routes/web.php` e adicione a rota no final do arquivo:

```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
```

**O que essa rota faz:**
- Tenta conectar no PostgreSQL via `getPdo()`
- Se funcionar, retorna `{"status": "ok"}` com HTTP 200
- Se falhar, retorna `{"status": "error"}` com HTTP 500
- Load balancers usam essa resposta para decidir se enviam trafego para o container

```bash
# Commitar health check
cd ~/laravel_ai
git add .
git commit -m "feat: add health check route"
```

---

## Passo 5 — Criar o Docker Compose de producao

Crie `compose-prod.yaml` na raiz do projeto (`codereview-ai/compose-prod.yaml`):

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - '80:80'
    environment:
      APP_ENV: production
      APP_DEBUG: 'false'
      APP_KEY: '${APP_KEY}'
      DB_CONNECTION: pgsql
      DB_HOST: pgsql
      DB_PORT: 5432
      DB_DATABASE: '${DB_DATABASE}'
      DB_USERNAME: '${DB_USERNAME}'
      DB_PASSWORD: '${DB_PASSWORD}'
      GEMINI_API_KEY: '${GEMINI_API_KEY}'
      AI_PROVIDER: gemini
      QUEUE_CONNECTION: database
    depends_on:
      pgsql:
        condition: service_healthy

  pgsql:
    image: 'pgvector/pgvector:pg18'
    environment:
      POSTGRES_DB: '${DB_DATABASE}'
      POSTGRES_USER: '${DB_USERNAME}'
      POSTGRES_PASSWORD: '${DB_PASSWORD}'
    volumes:
      - 'pgdata:/var/lib/postgresql/data'
    healthcheck:
      test: ['CMD-SHELL', 'pg_isready -U ${DB_USERNAME}']
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  pgdata:
```

**Pontos importantes:**
- `depends_on` com `condition: service_healthy` — o app so sobe depois que o PostgreSQL estiver pronto
- Variaveis via `${...}` — lidas do `.env` local ou de secrets do CI/CD
- `pgvector/pgvector:pg18` — mesma imagem de desenvolvimento, com suporte a vetores
- `QUEUE_CONNECTION: database` — queue workers processam jobs via tabela do PostgreSQL

```bash
# Commitar compose de producao
cd ~/laravel_ai
git add .
git commit -m "feat: add compose-prod.yaml for production deployment"
```

---

## Passo 6 — Build e teste local

### 6.1 — Build da imagem

```bash
docker build -t codereview-ai:latest .
```

Aguarde o build completar. Na primeira vez pode demorar alguns minutos por conta do download das imagens base e instalacao das extensoes PHP.

### 6.2 — Subir os containers de producao

```bash
docker compose -f compose-prod.yaml up -d
```

### 6.3 — Rodar migrations

```bash
docker compose -f compose-prod.yaml exec app php artisan migrate --force
```

### 6.4 — Importar docs para RAG

```bash
docker compose -f compose-prod.yaml exec app php artisan docs:import PSR-12
docker compose -f compose-prod.yaml exec app php artisan docs:import OWASP
```

### 6.5 — Verificar logs

```bash
docker compose -f compose-prod.yaml logs -f app
```

### 6.6 — Testar o health check

```bash
curl http://localhost/health
```

Deve retornar:

```json
{"status":"ok"}
```

### 6.7 — Parar os containers (quando terminar)

```bash
docker compose -f compose-prod.yaml down
```

---

## Passo 7 — Deploy na AWS (conceito)

Para deploy em producao na AWS, o fluxo e:

```bash
# Login no ECR
aws ecr get-login-password | docker login --username AWS --password-stdin <account>.dkr.ecr.<region>.amazonaws.com

# Tag e push
docker tag codereview-ai:latest <account>.dkr.ecr.<region>.amazonaws.com/codereview-ai:latest
docker push <account>.dkr.ecr.<region>.amazonaws.com/codereview-ai:latest

# Deploy via ECS, EKS ou App Runner
```

> Este e um exemplo conceitual. No Capitulo 13 vamos detalhar a API REST com Sanctum e Swagger. A infraestrutura completa com Terraform sera abordada em capitulos futuros.

---

## Checklist de deploy

Antes de colocar em producao, verifique cada item:

- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e configurada
- [ ] `GEMINI_API_KEY` configurada (ou OpenAI/Anthropic se preferir)
- [ ] `AI_PROVIDER` definido no `.env`
- [ ] PostgreSQL com pgvector acessivel
- [ ] Migrations rodadas (`artisan migrate --force`)
- [ ] Knowledge base populada (`artisan docs:import`)
- [ ] Assets compilados (`npm run build`)
- [ ] Caches gerados (`config:cache`, `route:cache`, etc.)
- [ ] Queue worker rodando (via Supervisor)
- [ ] Storage link criado (`artisan storage:link`)
- [ ] HTTPS configurado (via load balancer ou Nginx)
- [ ] Logs direcionados para stdout/stderr

---

## Passo 8 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: deploy docker production setup complete"

# Push da branch
git push -u origin feat/cap12-deploy

# Criar Pull Request
gh pr create --title "feat: deploy com Docker para producao" --body "Capitulo 12 - Dockerfile multi-stage, Nginx, Supervisor, compose-prod.yaml, health check e checklist de deploy"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `docker/nginx/default.conf` | Configuracao Nginx: proxy reverso para PHP-FPM, cache de assets |
| `docker/supervisor/supervisord.conf` | Supervisor: gerencia PHP-FPM, Nginx e queue workers |
| `Dockerfile` | Multi-stage build: Node.js compila assets, PHP-FPM roda a app |
| `compose-prod.yaml` | Docker Compose de producao: app + PostgreSQL com pgvector |
| `routes/web.php` (editado) | Rota `/health` para load balancers e orquestradores |

---

## Conclusao do tutorial

Parabens! Voce construiu o **CodeReview AI** do zero usando **Laravel AI SDK**:

1. **Introducao** — Pilares de AI Engineering, Laravel AI SDK
2. **Setup** — Docker-first, Sail, pgvector, Gemini API key
3. **Banco** — PostgreSQL 18, pgvector, schema completo
4. **Models** — Eloquent com Project, CodeReview, DocEmbedding
5. **Rotas** — Livewire Volt e single-file components
6. **Design System** — 20+ componentes com severity badges e code blocks
7. **Auth** — Login, registro e admin
8. **Agents + Structured Output** — `CodeAnalyst` Agent com `HasStructuredOutput` e `JsonSchema`
9. **RAG** — `Ai::embeddings()` + pgvector + `SearchDocsKnowledgeBase` Tool
10. **Multi-Agents** — `CodeMentor` orquestra 3 Agent classes via `HasTools`
11. **Jobs** — Filas assincronas + `FakeAi` para testes
12. **Deploy** — Docker multi-stage para producao

### Stack completa

| Camada | Tecnologia |
|--------|-----------|
| AI SDK | Laravel AI SDK (`laravel/ai`) |
| Agents | `CodeAnalyst`, `CodeMentor`, `ArchitectureAnalyst`, `PerformanceAnalyst`, `SecurityAnalyst` |
| Tools | `SearchDocsKnowledgeBase`, `AnalyzeArchitecture/Performance/Security`, `StoreImprovements` |
| RAG | `Ai::embeddings()` + pgvector + HNSW |
| Provider | Google Gemini (gratis) |
| Framework | Laravel 13 + Livewire 4.2 Volt |
| DB | PostgreSQL 18 + pgvector 0.8 |
| Deploy | Docker multi-stage + Supervisor |

## Proximo capitulo

No [Capitulo 13 — API REST e Swagger](13-api-swagger.md) vamos criar endpoints REST com Sanctum e documentar com Swagger/OpenAPI.

### Para ir alem

- Adicionar `RemembersConversations` para historico de analises por usuario
- Implementar streaming de respostas via `->stream()`
- Adicionar failover entre providers (`Lab::Gemini` -> `Lab::OpenAI`)
- Implementar reranking para melhorar resultados do RAG
- Adicionar analise de repositorios completos via GitHub API
- Implementar websockets para updates em tempo real (Laravel Reverb)
- Suportar mais linguagens com prompts especializados (Go, Rust, Python)
- Integrar com CI/CD para code review automatico em PRs
- Adicionar observabilidade (OpenTelemetry, tracing via Events)
- Explorar Image/Audio features do SDK para gerar diagramas e explicacoes em audio

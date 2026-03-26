# Capitulo 12 — Deploy com Docker

> **Este capitulo cobre o pilar: AI Infrastructure (8)**

## Dockerfile multi-stage

O projeto usa um Dockerfile com **2 stages** otimizado para producao:

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

### Por que multi-stage?

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

## Nginx

```nginx
# docker/nginx/default.conf

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

## Supervisor

```ini
; docker/supervisor/supervisord.conf

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

O Supervisor gerencia 3 processos:
1. **PHP-FPM** — processa requests PHP (20 workers estaticos)
2. **Nginx** — proxy reverso e arquivos estaticos
3. **Queue Worker** — 2 workers para processar jobs dos Agents de IA

## Docker Compose para producao

```yaml
# compose-prod.yaml

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

## Build e deploy

### Build local

```bash
# Build da imagem
docker build -t codereview-ai:latest .

# Testar localmente
docker compose -f compose-prod.yaml up -d

# Rodar migrations
docker compose -f compose-prod.yaml exec app php artisan migrate --force

# Importar docs para RAG
docker compose -f compose-prod.yaml exec app php artisan docs:import PSR-12
docker compose -f compose-prod.yaml exec app php artisan docs:import OWASP

# Ver logs
docker compose -f compose-prod.yaml logs -f app
```

### Deploy na AWS (conceito)

```bash
# Login no ECR
aws ecr get-login-password | docker login --username AWS --password-stdin <account>.dkr.ecr.<region>.amazonaws.com

# Tag e push
docker tag codereview-ai:latest <account>.dkr.ecr.<region>.amazonaws.com/codereview-ai:latest
docker push <account>.dkr.ecr.<region>.amazonaws.com/codereview-ai:latest

# Deploy via ECS, EKS ou App Runner
```

## Health check

```php
// Adicione em routes/web.php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
```

## Checklist de deploy

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

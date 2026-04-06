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

CMD ["/entrypoint.sh"]
```

Crie `docker/entrypoint.sh`:

```bash
#!/bin/sh
set -e

# Regenerar caches com as variaveis de ambiente do container
php /var/www/html/artisan config:cache > /dev/null 2>&1
php /var/www/html/artisan route:cache > /dev/null 2>&1
php /var/www/html/artisan view:cache > /dev/null 2>&1
php /var/www/html/artisan event:cache > /dev/null 2>&1

# Iniciar Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

> **Por que o entrypoint?** O `config:cache` gerado durante o build usa variaveis padrao (sem `.env`). O entrypoint regenera os caches ao iniciar o container com as variaveis reais do `--env-file`. Sem isso, o Laravel tenta usar Redis (padrao) em vez de database para cache/session/queue e o container falha.

> **Por que `> /dev/null`?** O output do `artisan` corromperia os headers HTTP se fosse para stdout antes do Nginx processar a primeira request.

**Pontos importantes:**
- `COPY package.json package-lock.json ./` primeiro — aproveita cache de camadas do Docker
- `composer install --no-dev` — nao instala dependencias de desenvolvimento em producao
- `validate_timestamps=0` — OPcache nao verifica se arquivos mudaram (mais rapido em producao)
- `pm = static` com `max_children = 20` — pool fixo de workers PHP-FPM para performance previsivel
- `mkdir -p /var/log/supervisor` — diretorio necessario para o Supervisor gravar logs (nao existe por padrao no alpine)

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

## Passo 7 — Deploy na AWS

Duas opcoes dependendo do seu objetivo:

| | Opcao A: EC2 + Docker | Opcao B: ECS Fargate |
|--|----------------------|---------------------|
| **Custo** | Gratis (free tier t3.micro) | ~$15-30/mes |
| **Gerenciamento** | Voce cuida do SO e Docker | AWS gerencia tudo |
| **Complexidade** | Maior (SSH, updates) | Menor |
| **Ideal para** | Aprendizado, projetos pessoais | Producao com escala |

---

### Opcao A — EC2 + Docker (Free Tier)

#### 7.A.1 — Criar conta AWS e configurar alertas de faturamento

Acesse [aws.amazon.com](https://aws.amazon.com) e crie uma conta gratuita. Voce precisara de cartao de credito para cadastro, mas o free tier **nao cobra** enquanto estiver dentro dos limites.

> **Free Tier t3.micro:** 750 horas/mes por 12 meses. Uma instancia rodando 24/7 usa exatamente 720h/mes — dentro do limite.

Apos criar a conta, configure alertas de faturamento para evitar surpresas:

1. Acesse **Billing and Cost Management** no menu do usuario (canto superior direito)
2. Clique em **Alertas e notificacoes** → **Criar alerta de uso de cobrança**
3. Configure um alerta para $1 — qualquer cobrança inesperada vai te notificar

#### 7.A.2 — Criar instancia EC2

No console AWS, busque por **EC2** na barra de pesquisa e acesse o servico.

1. Clique em **Instancias** no menu lateral → **Executar instancias**

2. Preencha as configuracoes:
   - **Nome:** `codereview-ai`
   - **AMI:** Amazon Linux 2023 (elegivel para free tier)
   - **Tipo de instancia:** `t3.micro` (elegivel para free tier)
   - **Par de chaves:** Clique em **Criar novo par de chaves**
     - Nome: `codereview-ai-key`
     - Tipo: RSA
     - Formato: `.pem`
     - Salve o arquivo em `~/.ssh/codereview-ai-key.pem`

3. Em **Configuracoes de rede**, clique em **Editar** e adicione regras:
   - Regra 1: SSH (porta 22) — Origem: `Meu IP`
   - Regra 2: HTTP (porta 80) — Origem: `Qualquer lugar (0.0.0.0/0)`

4. Clique em **Executar instancia**

Apos alguns segundos, a instancia aparece no painel com estado **Executando**. Anote o **Endereco IPv4 publico** — voce vai usar bastante.

> **Hibernar vs Parar:** Quando precisar parar de usar, clique em **Estado da instancia → Parar**. O disco e preservado (sem custo adicional no free tier). Ao parar, o IP publico muda — isso e normal.

#### 7.A.3 — Conectar via SSH e instalar Docker

No seu terminal WSL2 (ou PowerShell no Windows):

```bash
# Dar permissao correta para a chave
chmod 400 ~/.ssh/codereview-ai-key.pem

# Conectar na instancia (substitua pelo IP publico da sua instancia)
ssh -i ~/.ssh/codereview-ai-key.pem ec2-user@<IP_PUBLICO>
```

Dentro da instancia EC2 (após conectar via SSH), instale o Docker:

```bash
# Todos esses comandos rodam na instancia EC2
sudo yum update -y
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker ec2-user
```

Desconecte e reconecte para as permissoes do grupo `docker` entrarem em vigor:

```bash
exit
ssh -i ~/.ssh/codereview-ai-key.pem ec2-user@<IP_PUBLICO>

# Verificar que Docker esta funcionando
docker ps
```

Deve retornar a lista vazia (sem erro):

```
CONTAINER ID   IMAGE     COMMAND   CREATED   STATUS    PORTS     NAMES
```

#### 7.A.4 — Instalar AWS CLI v2 na instancia EC2

```bash
# Ainda dentro da instancia EC2
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Verificar instalacao
aws --version
# aws-cli/2.x.x Python/3.x.x Linux/...
```

#### 7.A.5 — Criar usuario IAM para acesso CLI

Por seguranca, nunca use as credenciais do usuario root da AWS no CLI. Crie um usuario IAM dedicado:

1. No console AWS, busque **IAM** → **Users** → **Criar usuario**

2. Configure o usuario:
   - **Nome:** `codereview-ai-deploy`
   - Marque **Fornecer acesso de usuario ao Console AWS**: NAO (so precisamos de acesso CLI)
   - Clique em **Proximo**

3. Em **Definir permissoes**, escolha **Anexar politicas diretamente** e adicione:
   - Na barra de busca, pesquise `AmazonEC2ContainerRegistryFullAccess` e marque o checkbox
   - Limpe a busca, pesquise `AmazonEC2FullAccess` e marque o checkbox
   - Com as 2 politicas marcadas (2/1474), clique em **Proximo**

4. Em **Revisar e criar**, confira as 2 politicas listadas e clique em **Criar usuario**

5. Clique no usuario criado → **Credenciais de seguranca** → **Criar chave de acesso**
   - Escolha **Interface de linha de comando (CLI)**
   - Confirme o aviso e clique em **Proximo**
   - Clique em **Criar chave de acesso**

6. **IMPORTANTE:** Copie o **Access Key ID** e clique em **Mostrar** para ver o **Secret Access Key**. Voce so vera o Secret uma vez — anote os dois antes de fechar a tela.

> **Guarde suas credenciais com cuidado.** Nunca commite o Access Key em repositorios Git. Use `.gitignore` ou variaveis de ambiente.

#### 7.A.6 — Configurar AWS CLI no seu computador (WSL2)

No terminal WSL2 do seu computador (nao na instancia EC2):

```bash
aws configure
```

Preencha com as informacoes:

```
AWS Access Key ID [None]: AKIA...          # Cole o Access Key ID do passo anterior
AWS Secret Access Key [None]: ...          # Cole o Secret Access Key
Default region name [None]: us-east-2     # Regiao Ohio (onde criamos a instancia)
Default output format [None]: json
```

Confirme que funcionou:

```bash
aws sts get-caller-identity
```

Deve retornar algo como:

```json
{
    "UserId": "AIDA...",
    "Account": "<ACCOUNT_ID>",
    "Arn": "arn:aws:iam::<ACCOUNT_ID>:user/codereview-ai-deploy"
}
```

#### 7.A.7 — Criar repositorio no ECR e enviar a imagem

> **Terminal:** WSL2 (seu computador), dentro de `codereview-ai/`

O **ECR (Elastic Container Registry)** e o repositorio privado de imagens Docker da AWS.

```bash
# Criar repositorio no ECR (na regiao us-east-2)
aws ecr create-repository \
    --repository-name codereview-ai \
    --region us-east-2
```

Retornara um JSON com a URL do repositorio. Anote o campo `repositoryUri`:

```
<ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai
```

Agora faca o build e o push — **todos esses comandos rodam no WSL2**:

```bash
# Build da imagem (dentro de codereview-ai/)
docker build -t codereview-ai:latest .

# Login no ECR
aws ecr get-login-password --region us-east-2 \
    | docker login --username AWS --password-stdin \
      <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com

# Tag da imagem com a URL do ECR
docker tag codereview-ai:latest \
    <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai:latest

# Push para o ECR
docker push \
    <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai:latest
```

#### 7.A.8 — Configurar e rodar os containers na EC2

> **Terminal:** Abra um **novo terminal WSL2** (nao o mesmo onde fez o build) e conecte na EC2:

```bash
# Pegue o IP atual no console AWS > EC2 > Instancias > Endereco IPv4 publico
ssh -i ~/.ssh/codereview-ai-key.pem ec2-user@<IP_PUBLICO>
```

> **Atencao:** o IP publico muda toda vez que a instancia e parada e reiniciada. Sempre pegue o valor atualizado no console AWS antes de conectar.

A partir daqui, **todos os comandos rodam dentro da instancia EC2**:

Configure o AWS CLI na instancia com as mesmas credenciais do passo 7.A.6:

```bash
aws configure
# Preencha com o mesmo Access Key ID, Secret, regiao us-east-2, formato json
```

Crie uma rede Docker para os containers se comunicarem:

```bash
docker network create codereview-net
```

Suba o container do PostgreSQL com pgvector:

```bash
docker run -d \
    --name pgsql \
    --network codereview-net \
    -e POSTGRES_DB=codereview \
    -e POSTGRES_USER=postgres \
    -e POSTGRES_PASSWORD=password \
    -v pgdata:/var/lib/postgresql \
    pgvector/pgvector:pg18
```

> **Importante:** Use `/var/lib/postgresql` (sem `/data`) no volume — o pgvector pg18+ requer esse caminho.

Gere o `APP_KEY` antes de criar o `.env`:

```bash
# Gerar APP_KEY (rode na EC2)
docker run --rm <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai:latest php artisan key:generate --show
# Retorna algo como: base64:xYz123.../abc+def=
```

Crie o arquivo `.env` de producao substituindo todos os valores entre `<>` pelos valores reais:

```bash
cat > .env << 'EOF'
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:COLE_O_VALOR_GERADO_ACIMA
APP_URL=http://COLE_O_IP_PUBLICO_DA_INSTANCIA
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=codereview
DB_USERNAME=postgres
DB_PASSWORD=password
GEMINI_API_KEY=COLE_SUA_GEMINI_API_KEY
AI_PROVIDER=gemini
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
EOF
```

Ou use `sed` para preencher cada variavel individualmente:

```bash
sed -i 's|APP_KEY=.*|APP_KEY=base64:COLE_O_VALOR_GERADO|' .env
sed -i 's|APP_URL=.*|APP_URL=http://<IP_PUBLICO>|' .env
sed -i 's|DB_HOST=.*|DB_HOST=pgsql|' .env
sed -i 's|GEMINI_API_KEY=.*|GEMINI_API_KEY=<SUA_GEMINI_KEY>|' .env
```

Verifique se o `.env` foi criado corretamente:

```bash
cat .env
```

> **Atencao:** Nunca suba o container com valores placeholder (`base64:...`, `<IP_PUBLICO>`) — o container vai entrar em loop de restart por `APP_KEY` invalida.

> **Atencao:** Inclua sempre `CACHE_STORE=database` e `SESSION_DRIVER=database`. Sem esses valores, o Laravel tenta usar Redis (padrao) e o container falha pois nao ha Redis configurado.

Faca o login no ECR, pull da imagem e rode o container da app — **todos esses comandos rodam na instancia EC2**:

```bash
# Login no ECR (na EC2)
aws ecr get-login-password --region us-east-2 \
    | docker login --username AWS --password-stdin \
      <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com

# Pull da imagem
docker pull <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai:latest

# Rodar o container da app na mesma rede do banco
docker run -d \
    --name codereview-ai \
    --network codereview-net \
    -p 80:80 \
    --env-file .env \
    --restart unless-stopped \
    <ACCOUNT_ID>.dkr.ecr.us-east-2.amazonaws.com/codereview-ai:latest
```

Aguarde ~10 segundos para o banco inicializar e rode as migrations, seeds e publique os assets do Livewire:

```bash
sleep 10 && docker exec codereview-ai php artisan migrate --force
docker exec codereview-ai php artisan db:seed --class=LookupSeeder --force
docker exec codereview-ai php artisan livewire:publish --assets
docker exec codereview-ai php artisan storage:link
```

> **Por que o LookupSeeder?** A tabela `project_statuses` (e outras tabelas de lookup) precisa ser populada antes de criar projetos. Sem isso, o formulario de novo projeto retorna erro 500 por violacao de chave estrangeira (`projects_project_status_id_foreign`).

#### 7.A.9 — Verificar o deploy

```bash
# Ver logs do container
docker logs codereview-ai --tail=20

# Testar health check (da instancia EC2)
curl http://localhost/health
# {"status":"ok"}

# Testar de fora (no seu computador WSL2)
curl http://<IP_PUBLICO>/health
# {"status":"ok"}
```

> **Importante:** Sempre que alterar o `.env` na EC2, use `docker stop/rm` + `docker run` para recriar o container. O `docker restart` **nao relê** o `--env-file` — ele usa as variaveis do momento em que o container foi criado pela primeira vez.

Acesse `http://<IP_PUBLICO>` no navegador — a aplicacao deve estar rodando.

> **Lembrete:** O IP publico muda toda vez que voce para e reinicia a instancia. Para um IP fixo, use um **Elastic IP** (gratuito enquanto associado a uma instancia rodando).

> **Para parar sem cobrar:** No console AWS, selecione a instancia → **Estado da instancia** → **Interromper**. O disco e preservado e nao ha cobrança de compute enquanto parada.

---

### Opcao B — ECS Fargate (Producao)

**1. Build e push para ECR:**

```bash
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin <account>.dkr.ecr.us-east-1.amazonaws.com
docker tag codereview-ai:latest <account>.dkr.ecr.us-east-1.amazonaws.com/codereview-ai:latest
docker push <account>.dkr.ecr.us-east-1.amazonaws.com/codereview-ai:latest
```

**2. Criar Task Definition no ECS:**

```json
{
  "family": "codereview-ai",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "512",
  "memory": "1024",
  "containerDefinitions": [{
    "name": "app",
    "image": "<account>.dkr.ecr.us-east-1.amazonaws.com/codereview-ai:latest",
    "portMappings": [{ "containerPort": 80 }],
    "environment": [
      { "name": "APP_ENV", "value": "production" }
    ]
  }]
}
```

**3. Criar Service e aguardar deploy:**

```bash
# Via console AWS: ECS -> Clusters -> Create Service
# Launch type: FARGATE
# Task definition: codereview-ai
# Desired tasks: 1
# Load balancer: Application Load Balancer (opcional)
```

> A infraestrutura completa com Terraform (VPC, RDS, ElastiCache, ECS) sera abordada em capitulos futuros.

---

## Checklist de deploy

Antes de colocar em producao, verifique cada item:

- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e configurada
- [ ] `GEMINI_API_KEY` configurada (ou OpenAI/Anthropic se preferir)
- [ ] `AI_PROVIDER` definido no `.env`
- [ ] PostgreSQL com pgvector acessivel
- [ ] Migrations rodadas (`artisan migrate --force`)
- [ ] Lookup tables populadas (`artisan db:seed --class=LookupSeeder`)
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
| `docker/supervisor/supervisord.conf` | Supervisor: gerencia PHP-FPM, Nginx e 2 queue workers |
| `Dockerfile` | Multi-stage build: Node.js compila assets, PHP-FPM roda a app |
| `compose-prod.yaml` | Docker Compose de producao: app + PostgreSQL com pgvector |
| `routes/web.php` (editado) | Rota `/health` para load balancers e orquestradores |

**Infraestrutura AWS (Opcao A — Free Tier):**

| Recurso | Servico AWS | Custo |
|---------|------------|-------|
| Servidor | EC2 t3.micro | Gratis (750h/mes por 12 meses) |
| Imagem Docker | ECR | Gratis (500MB/mes) |
| Rede | VPC padrao | Gratis |
| IP publico | IPv4 dinamico | Gratis enquanto instancia rodando |

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

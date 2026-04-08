# CodeReview AI

SaaS de revisão de código com IA construído com Laravel 13 + Laravel AI SDK.
Analisa código submetido por múltiplos agentes especializados e retorna findings de arquitetura, performance e segurança com score e plano de melhorias.

---

## Arquitetura de IA

![8 Pilares da Engenharia de IA](../docs/assets/arquitetura-ia.png)

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | Livewire 4 + Volt, Tailwind CSS |
| IA | Laravel AI SDK 0.4.x, Gemini 2.5 Flash |
| Embeddings | Gemini gemini-embedding-001 (768 dims) |
| Banco | PostgreSQL + pgvector |
| Queue | Laravel Queue (database driver) |
| Container (dev) | Laravel Sail |
| Container (prod) | Docker multi-stage + Supervisor |
| Testes | Pest PHP |

---

## Agentes

| Agente | Responsabilidade |
|---|---|
| `CodeAnalyst` | Analisa estrutura geral, gera summary e score |
| `SecurityAnalyst` | Pilar segurança — OWASP Top 10 |
| `ArchitectureAnalyst` | Pilar arquitetura — SOLID, PSR-12, Clean Code |
| `PerformanceAnalyst` | Pilar performance — N+1, cache, algoritmos |
| `CodeMentor` | Orquestra os analistas e gera plano de melhorias |

Busca semântica via `SearchDocsKnowledgeBase` (RAG com pgvector).

Jobs assíncronos:
- `AnalyzeCodeJob` — dispara `CodeAnalyst`
- `GenerateImprovementsJob` — dispara multi-agentes + `CodeMentor`

---

## Pré-requisitos

- Docker + Docker Compose
- PHP 8.5 (via Sail)
- Chave de API do Google Gemini

---

## Instalação

```bash
# 1. Clonar e instalar dependências
git clone <repo>
cd codereview-ai
composer install

# 2. Configurar ambiente
cp .env.example .env
# Preencher: DB_*, GEMINI_API_KEY

# 3. Subir containers
./vendor/bin/sail up -d

# 4. Migrations e seeds
sail artisan migrate
sail artisan db:seed --class=LookupSeeder

# 5. Importar knowledge base (PSR-12)
sail artisan docs:import psr-12

# 6. Rodar worker de filas
sail artisan queue:work --tries=3 -v
```

---

## Testes

```bash
sail artisan test
```

57 testes, cobertura de agents, jobs, RAG e endpoints REST.

---

## Documentação da API

Disponível em `/docs/api` (Scramble) no ambiente `local`.

---

## Tutorial

O repositório acompanha 14 capítulos em `docs/` que documentam a construção do projeto passo a passo, incluindo erros reais encontrados e suas correções.

---

## Licença

MIT

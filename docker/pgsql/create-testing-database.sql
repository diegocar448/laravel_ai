SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec

-- Habilitar pgvector no banco principal e testing
\c laravel
CREATE EXTENSION IF NOT EXISTS vector;

\c testing
CREATE EXTENSION IF NOT EXISTS vector;
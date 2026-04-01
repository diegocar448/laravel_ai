<?php

namespace App\Console\Commands;

use App\Models\DocEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class ImportDocs extends Command
{
    protected $signature = 'docs:import {source : Nome da fonte (ex: psr-12, owasp, laravel-best-practices)}';

    protected $description = 'Importa documentacoes JSON para a knowledge base com embeddings vetoriais';

    public function handle(): int
    {
        $source = $this->argument('source');
        $filePath = base_path("docs-knowledge-base/{$source}.json");

        if (! File::exists($filePath)) {
            $this->error("Arquivo nao encontrado: {$filePath}");
            $this->info('Arquivos disponiveis:');

            collect(File::files(base_path('docs-knowledge-base')))
                ->each(fn ($file) => $this->line("  - {$file->getFilenameWithoutExtension()}"));

            return self::FAILURE;
        }

        $docs = json_decode(File::get($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Erro ao parsear JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info("Importando {$source}: " . count($docs) . " documentos");
        $bar = $this->output->createProgressBar(count($docs));
        $bar->start();

        $imported = 0;

        foreach ($docs as $doc) {
            // Verificar se ja existe para evitar duplicatas
            $exists = DocEmbedding::where('source', $source)
                ->where('title', $doc['title'])
                ->exists();

            if ($exists) {
                $bar->advance();
                continue;
            }

            // Gerar embedding via Embeddings::for()->generate()
            $result = Embeddings::for([$doc['content']])
                ->dimensions(768)
                ->generate(Lab::Gemini, 'gemini-embedding-001');

            // Salvar no banco com o vetor
            DocEmbedding::create([
                'source'    => $source,
                'title'     => $doc['title'],
                'content'   => $doc['content'],
                'embedding' => $result->first(),
                'category'  => $doc['category'],
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Importados {$imported} documentos de {$source} com sucesso!");

        return self::SUCCESS;
    }
}

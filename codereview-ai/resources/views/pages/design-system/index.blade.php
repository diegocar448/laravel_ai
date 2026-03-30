<?php

use Livewire\Volt\Component;

new class extends Component
{
    //
}; ?>

<x-layouts::guest>
    <x-slot:title>Design System — CodeReview AI</x-slot:title>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            <!-- Header -->
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100">Design System</h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Todos os componentes do CodeReview AI</p>
                <div class="mt-4">
                    <x-theme-toggle />
                </div>
            </div>

            <!-- Buttons -->
            <x-section title="Buttons" description="Variantes e tamanhos disponiveis">
                <div class="flex flex-wrap gap-4">
                    <x-button variant="primary">Primary</x-button>
                    <x-button variant="secondary">Secondary</x-button>
                    <x-button variant="danger">Danger</x-button>
                    <x-button variant="ghost">Ghost</x-button>
                </div>
                <div class="flex flex-wrap gap-4 mt-4">
                    <x-button size="sm">Small</x-button>
                    <x-button size="md">Medium</x-button>
                    <x-button size="lg">Large</x-button>
                </div>
            </x-section>

            <!-- Alerts -->
            <x-section title="Alerts" description="Notificacoes e mensagens de feedback">
                <div class="space-y-4">
                    <x-alert type="info">Informacao: sua analise esta sendo processada.</x-alert>
                    <x-alert type="success">Sucesso: code review finalizado com nota 8.5!</x-alert>
                    <x-alert type="warning">Aviso: encontramos 3 pontos de atencao no seu codigo.</x-alert>
                    <x-alert type="danger">Erro: falha ao conectar com o servico de IA.</x-alert>
                    <x-alert type="info" :dismissible="true">Este alerta pode ser fechado.</x-alert>
                </div>
            </x-section>

            <!-- Severity Badges -->
            <x-section title="Severity Badges" description="Indicadores de severidade dos findings">
                <div class="flex flex-wrap gap-4">
                    <x-severity-badge severity="low" />
                    <x-severity-badge severity="medium" />
                    <x-severity-badge severity="high" />
                    <x-severity-badge severity="critical" />
                </div>
            </x-section>

            <!-- Cards -->
            <x-section title="Cards" description="Containers de conteudo">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-card>
                        <x-card.header>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Card com Header</h3>
                        </x-card.header>
                        <x-card.body>
                            <p class="text-gray-600 dark:text-gray-400">
                                Este e um card com header e body separados.
                            </p>
                        </x-card.body>
                    </x-card>

                    <x-card class="p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Card Simples</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Card sem sub-componentes, usando padding direto.
                        </p>
                    </x-card>
                </div>
            </x-section>

            <!-- Form Components -->
            <x-section title="Form Components" description="Campos de formulario">
                <x-card>
                    <x-card.body>
                        <div class="space-y-4">
                            <x-form.input label="Nome do Projeto" name="name" placeholder="Ex: Minha API REST" />
                            <x-form.select label="Linguagem" name="language" :options="[
                                'php' => 'PHP',
                                'javascript' => 'JavaScript',
                                'python' => 'Python',
                                'go' => 'Go',
                                'rust' => 'Rust',
                            ]" />
                            <x-form.textarea label="Descricao" name="description" placeholder="Descreva seu projeto..." />
                            <x-form.code-editor label="Codigo" name="code" language="php" placeholder="<?php echo 'Hello World';" />
                        </div>
                    </x-card.body>
                </x-card>
            </x-section>

            <!-- Code Block -->
            <x-section title="Code Block" description="Exibicao de codigo com destaque">
                <x-code-block language="php" filename="app/Services/PaymentService.php">
public function charge(User $user, float $amount): bool
{
    return DB::transaction(function () use ($user, $amount) {
        $user->balance -= $amount;
        $user->save();
        return true;
    });
}
                </x-code-block>
            </x-section>

            <!-- Table -->
            <x-section title="Table" description="Tabela responsiva">
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pilar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Severidade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descricao</th>
                        </tr>
                    </x-slot:head>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Architecture</td>
                        <td class="px-6 py-4"><x-severity-badge severity="medium" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Falta inversao de dependencia no controller</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Security</td>
                        <td class="px-6 py-4"><x-severity-badge severity="critical" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">SQL Injection via input nao sanitizado</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Performance</td>
                        <td class="px-6 py-4"><x-severity-badge severity="high" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">N+1 query no loop de relatorios</td>
                    </tr>
                </x-table>
            </x-section>

            <!-- Kanban Components -->
            <x-section title="Kanban Components" description="Componentes do board de melhorias">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- ToDo Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="ToDo" :count="1" color="blue" />
                        <div class="space-y-3">
                            <div class="bg-white dark:bg-gray-700 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-2">
                                    <x-severity-badge severity="high" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Refactor</span>
                                </div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Refatorar controller de projetos</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono">app/Http/Controllers/ProjectController.php</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-kanban-add-button label="Adicionar tarefa" />
                        </div>
                    </div>

                    <!-- InProgress Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="In Progress" :count="0" color="yellow" />
                        <x-kanban-empty-state message="Nenhuma tarefa em andamento" />
                    </div>

                    <!-- Done Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="Done" :count="0" color="green" />
                        <x-kanban-empty-state message="Nenhuma tarefa concluida" />
                    </div>
                </div>
            </x-section>

            <!-- Modal -->
            <x-section title="Modal" description="Dialog para acoes e confirmacoes">
                <x-button
                    variant="primary"
                    @click="$dispatch('open-modal', 'demo-modal')"
                >
                    Abrir Modal
                </x-button>

                <x-modal name="demo-modal" maxWidth="md">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            Confirmar acao
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            Tem certeza que deseja executar esta acao? Esta operacao nao pode ser desfeita.
                        </p>
                        <div class="flex justify-end gap-3">
                            <x-button variant="ghost" @click="$dispatch('close-modal', 'demo-modal')">
                                Cancelar
                            </x-button>
                            <x-button variant="danger" @click="$dispatch('close-modal', 'demo-modal')">
                                Confirmar
                            </x-button>
                        </div>
                    </div>
                </x-modal>
            </x-section>

        </div>
    </div>
</x-layouts::guest>

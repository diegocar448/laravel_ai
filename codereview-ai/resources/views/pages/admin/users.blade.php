<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(20),
        ];
    }
}
?>

<x-layouts.app>
    <x-section title="Usuarios" description="Lista de todos os usuarios do sistema">
        <x-table>
            <x-slot:head>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-mail</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primeiro Review</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primeiro Plano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admin</th>
                </tr>
            </x-slot:head>
            @foreach($users as $user)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->first_review_at?->format('d/m/Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->first_plan_at?->format('d/m/Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->is_admin ? 'Sim' : 'Nao' }}</td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </x-section>
</x-layouts.app>

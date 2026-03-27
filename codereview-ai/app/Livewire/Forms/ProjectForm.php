<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Project;
use App\Enums\ProjectStatusEnum;

class ProjectForm extends Form
{
    public string $name = '';
    public string $language = 'php';
    public string $code_snippet = '';
    public ?string $repository_url = null;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'language' => 'required|string|in:php,javascript,python,go,rust,java,typescript',
            'code_snippet' => 'required|string|min:50',
            'repository_url' => 'nullable|url',
        ];
    }

    public function store(): Project
    {
        $this->validate();

        return auth()->user()->projects()->create([
            'name' => $this->name,
            'language' => $this->language,
            'code_snippet' => $this->code_snippet,
            'repository_url' => $this->repository_url,
            'project_status_id' => ProjectStatusEnum::Active->value,
        ]);
    }
}

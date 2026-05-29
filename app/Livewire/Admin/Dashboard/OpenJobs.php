<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\AdminTask;
use Livewire\Component;

class OpenJobs extends Component
{
    public bool $autoRefresh = true;

    /** @var array<int, array<string, mixed>> */
    public array $jobs = [];

    public int $limit = 6;

    public function mount(bool $autoRefresh = true): void
    {
        $this->autoRefresh = $autoRefresh;
        $this->loadJobs();
    }

    public function render()
    {
        $this->loadJobs();

        return view('livewire.admin.dashboard.open-jobs');
    }

    public function loadJobs(): void
    {
        $this->jobs = AdminTask::query()
            ->where('status', AdminTask::STATUS_OPEN)
            ->with(['creator:id,name', 'assignedAdmin:id,name', 'context'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_at')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get()
            ->map(function (AdminTask $job) {
                return [
                    'task_type_text' => $job->task_type_text,
                    'context_text' => $job->context_text,
                    'status_text' => $job->status_text,
                    'priority_text' => $job->priority_text,
                    'assigned_admin_name' => $job->assignedAdmin?->name,
                    'due_at' => $job->due_at?->format('d.m.Y H:i'),
                    'is_overdue' => $job->due_at ? $job->due_at->isPast() : false,
                ];
            })
            ->values()
            ->all();
    }
}

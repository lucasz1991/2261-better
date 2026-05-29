<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Employees extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('employees.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(int $userId, bool $active): void
    {
        $user = User::findOrFail($userId);
        $user->status = $active;
        $user->save();
    }

    public function render()
    {
        $employees = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.employees', compact('employees'))->layout('layouts.master');
    }
}

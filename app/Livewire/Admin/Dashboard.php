<?php

namespace App\Livewire\Admin;

class Dashboard extends \App\Livewire\AdminDashboard
{
    public function render()
    {
        return view('livewire.admin.dashboard')->layout('layouts.admin');
    }
}

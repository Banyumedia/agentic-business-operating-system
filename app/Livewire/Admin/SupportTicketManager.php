<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTicketManager extends Component
{
    use WithPagination;

    public string $filterStatus = 'all';

    public ?int $resolvingId = null;

    public string $resolutionNote = '';

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function startResolve(int $id): void
    {
        $this->resolvingId = $id;
        $this->resolutionNote = '';
    }

    public function cancelResolve(): void
    {
        $this->resolvingId = null;
    }

    public function resolve(): void
    {
        $this->validate([
            'resolutionNote' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if ($this->resolvingId) {
            $ticket = SupportTicket::findOrFail($this->resolvingId);
            $ticket->update([
                'status' => 'resolved',
            ]);

            $this->resolvingId = null;
            session()->flash('success', "Tiket #{$ticket->ticket_number} berhasil diselesaikan.");
        }
    }

    public function render(): View
    {
        $query = SupportTicket::with('company')->latest();

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $tickets = $query->paginate(15);

        return view('livewire.admin.support-ticket-manager', [
            'tickets' => $tickets,
        ])->layout('components.layouts.module', ['title' => 'Support Tickets  Super Admin']);
    }
}

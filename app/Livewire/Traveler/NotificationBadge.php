<?php
namespace App\Livewire\Traveler;

use Livewire\Component;

class NotificationBadge extends Component
{
    public function render()
    {
        $count = auth()->check()
            ? auth()->user()->notifications()->where('is_read', false)->count()
            : 0;
        return view('livewire.traveler.notification-badge', ['count' => $count]);
    }
}

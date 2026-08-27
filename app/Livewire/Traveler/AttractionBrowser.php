<?php
namespace App\Livewire\Traveler;

use App\Models\Attraction;
use Livewire\Component;

class AttractionBrowser extends Component
{
    public string $search      = '';
    public string $destination = '';

    public function getAttractionsProperty()
    {
        $query = Attraction::query();
        if ($this->search)      $query->where('name', 'like', "%{$this->search}%");
        if ($this->destination) $query->where('destination', $this->destination);
        return $query->orderBy('name')->get();
    }

    public function getDestinationsProperty()
    {
        return Attraction::orderBy('destination')->pluck('destination')->unique()->values();
    }

    public function render()
    {
        return view('livewire.traveler.attraction-browser');
    }
}

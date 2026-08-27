<?php
namespace App\Livewire\Traveler;

use App\Models\Destination;
use Livewire\Component;

class DestinationBrowser extends Component
{
    public string $search  = '';
    public string $country = '';

    // Surfaces exactly the cities the manual trip planner's From/To
    // dropdowns offer (config/planner_cities.php — the same list, so the
    // two never drift apart) rather than only the admin-curated subset of
    // the ~260 pre-seeded reference cities, so every destination a
    // traveler can actually plan a trip to shows up here too.
    private function plannerCityNames(): array
    {
        $cities = config('planner_cities');
        return array_column(array_merge($cities['local'], $cities['intl']), 'name');
    }

    public function getDestinationsProperty()
    {
        $query = Destination::withCount('attractions')
            ->withAvg('attractions', 'rating')
            ->whereIn('name', $this->plannerCityNames());
        if ($this->search)  $query->where('name', 'like', "%{$this->search}%");
        if ($this->country) $query->where('country', $this->country);
        return $query->orderBy('name')->get();
    }

    public function getCountriesProperty()
    {
        return Destination::whereIn('name', $this->plannerCityNames())
            ->whereNotNull('country')
            ->orderBy('country')->pluck('country')->unique()->values();
    }

    public function render()
    {
        return view('livewire.traveler.destination-browser');
    }
}

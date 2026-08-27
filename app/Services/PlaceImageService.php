<?php
namespace App\Services;

use App\Models\Attraction;
use App\Models\Destination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaceImageService
{
    // Fetches a photo via SerpAPI (Google Images) and downloads it into
    // local storage — same disk/naming convention as a manual admin upload
    // — rather than storing a bare external URL, so it keeps working even
    // if the source link later breaks or blocks hotlinking. Shared by the
    // admin "Fetch Photo" buttons and the bulk-fill console commands (for
    // both destinations and attractions) so the download/store logic only
    // lives in one place.
    public function fetchForDestination(Destination $destination): bool
    {
        return $this->fetch($destination, $destination->name, $destination->country, 'travel destination', 'destination-images');
    }

    public function fetchForAttraction(Attraction $attraction): bool
    {
        return $this->fetch($attraction, $attraction->name, $attraction->destination, 'tourist attraction landmark', 'attraction-images');
    }

    private function fetch(Destination|Attraction $model, string $name, ?string $context, string $hint, string $folder): bool
    {
        $url = (new SerpApiService())->searchPlaceImage($name, $context, $hint);
        if (!$url) return false;

        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return false;
        }
        if (!$response->successful()) return false;

        $extension = match (true) {
            str_contains($response->header('Content-Type', ''), 'png')  => 'png',
            str_contains($response->header('Content-Type', ''), 'webp') => 'webp',
            default => 'jpg',
        };
        $filename = Str::slug($name) . '.' . $extension;
        Storage::disk('public')->put($folder . '/' . $filename, $response->body());
        $model->update(['image' => $folder . '/' . $filename]);
        return true;
    }
}

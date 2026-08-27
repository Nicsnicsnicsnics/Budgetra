<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SerperService
{
    private string $key;
    private string $endpoint = 'https://google.serper.dev/places';

    public function __construct()
    {
        $this->key = config('services.serper.key', '');
    }

    private function request(string $query, string $location = ''): ?array
    {
        if (!$this->key) return null;

        $cacheKey = 'serper_places_' . md5($query . $location);
        return Cache::remember($cacheKey, now()->addHours(12), function () use ($query, $location) {
            $payload = ['q' => $query, 'hl' => 'en', 'gl' => 'ph'];
            if ($location) $payload['location'] = $location;

            $response = Http::timeout(15)
                ->withHeaders(['X-API-KEY' => $this->key, 'Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            if (!$response->successful()) return null;
            return $response->json();
        });
    }

    private function fetchImages(string $query): array
    {
        if (!$this->key) return [];

        $cacheKey = 'serper_images_' . md5($query);
        $data = Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
            $response = Http::timeout(10)
                ->withHeaders(['X-API-KEY' => $this->key, 'Content-Type' => 'application/json'])
                ->post('https://google.serper.dev/images', ['q' => $query, 'hl' => 'en', 'gl' => 'ph', 'num' => 10]);
            if (!$response->successful()) return null;
            return $response->json();
        });

        return $data['images'] ?? [];
    }

    /**
     * No longer returns anything.
     *
     * This used to be the fallback whenever SerpApiService returned no
     * flights (quota exhausted, error, thin route). It fired a Serper web
     * search, THREW THE RESPONSE AWAY without reading it, and then invented
     * three flights out of thin air:
     *
     *   - price from rand(1500, 4000) etc., x1.8 for a round trip
     *   - duration hardcoded to 90 minutes for every route on earth
     *   - flight numbers from rand(100, 999)
     *   - departure/arrival picked from five fixed time slots
     *
     * Those were rendered in the same card as real SerpAPI results with no
     * way for the traveler to tell them apart, and the invented prices fed
     * straight into the trip budget, savings goals and estimates. A budgeting
     * app cannot quote made-up fares.
     *
     * Serper's /search endpoint returns generic web results, not structured
     * itineraries, so there is nothing here to parse into honest flight data.
     * Returning null lets the callers fall through to their existing "we
     * couldn't load flights right now" state, which is the truth.
     */
    public function searchFlights(string $fromCode, string $toCode, string $departDate, string $returnDate = ''): ?array
    {
        return null;
    }

    public function searchHotels(string $destination, string $checkIn, string $checkOut, int $nights, string $type = 'hotel'): ?array
    {
        $typeLabel = match($type) {
            'apartment' => 'apartments',
            'inn'       => 'inns and guesthouses',
            'resort'    => 'resorts',
            default     => 'hotels',
        };
        $data = $this->request("best {$typeLabel} in {$destination}", $destination);
        if (!$data) return null;

        $places = $data['places'] ?? [];
        if (empty($places)) return null;

        // Fetch a pool of images once for the whole destination+type query
        $imagePool = $this->fetchImages("best {$typeLabel} in {$destination}");

        $results = [];
        foreach ($places as $idx => $p) {
            $name = $p['title'] ?? null;
            if (!$name) continue;

            $rating  = (float)($p['rating'] ?? 3.5);
            $stars   = (int)round($rating);
            $address = $p['address'] ?? '';

            $imgEntry = $imagePool[$idx] ?? $imagePool[0] ?? null;
            $image    = $imgEntry['thumbnailUrl'] ?? $imgEntry['imageUrl'] ?? null;

            // Realistic Philippine hotel nightly rates by rating
            $nightly = match(true) {
                $rating >= 4.5 => rand(1800, 3500),
                $rating >= 4.0 => rand(900,  1800),
                $rating >= 3.5 => rand(500,   900),
                default        => rand(250,   500),
            };
            $total = $nightly * $nights;

            $rawType = strtolower($p['category'] ?? $p['type'] ?? '');
            $typeKey = match(true) {
                str_contains($rawType, 'apartment') || str_contains($rawType, 'condo') || str_contains($rawType, 'suite') => 'apartment',
                str_contains($rawType, 'inn') || str_contains($rawType, 'hostel') || str_contains($rawType, 'lodge') || str_contains($rawType, 'guesthouse') => 'inn',
                str_contains($rawType, 'resort') => 'resort',
                default => $type,
            };

            $results[] = [
                'name'      => $name,
                'stars'     => $stars,
                'image'     => $image,
                'nightly'   => $nightly,
                'total'     => $total,
                'nights'    => $nights,
                'dist'      => $address,
                'type'      => $typeKey,
                'typeLabel' => ucfirst($typeKey),
                'lat'       => $p['latitude']  ?? null,
                'lng'       => $p['longitude'] ?? null,
                'source'    => 'serper',
            ];
        }

        return $results ?: null;
    }

    public function searchRestaurants(string $destination, string $category = ''): ?array
    {
        $q = $category && $category !== 'All Cuisines'
            ? "{$category} restaurants in {$destination}"
            : "best restaurants in {$destination}";

        $data = $this->request($q, $destination);
        if (!$data) return null;

        $places = $data['places'] ?? [];
        if (empty($places)) return null;

        $imagePool = $this->fetchImages($q . ' food');

        $out = [];
        foreach ($places as $idx => $p) {
            $name = $p['title'] ?? null;
            if (!$name) continue;

            $priceLevel = strlen($p['priceLevel'] ?? '') ?: 2;
            $priceMin   = $priceLevel <= 1 ? 200 : ($priceLevel <= 2 ? 400 : 800);
            $priceMax   = $priceMin * 2;

            $imgEntry = $imagePool[$idx] ?? $imagePool[0] ?? null;
            $image    = $imgEntry['thumbnailUrl'] ?? $imgEntry['imageUrl'] ?? null;

            $out[] = [
                'name'     => $name,
                'cuisine'  => $p['category'] ?? ($category ?: 'Restaurant'),
                'city'     => $destination,
                'rating'   => $p['rating']   ?? null,
                'reviews'  => $p['ratingCount'] ?? null,
                'image'    => $image,
                'priceMin' => $priceMin,
                'priceMax' => $priceMax,
                'priceTag' => $p['priceLevel'] ?? '₱₱',
                'lat'      => $p['latitude']  ?? null,
                'lng'      => $p['longitude'] ?? null,
                'source'   => 'serper',
            ];
        }

        return $out ?: null;
    }

    public function searchAttractions(string $destination): ?array
    {
        $data = $this->request("tourist attractions things to do within 15km of {$destination}", $destination);
        if (!$data) return null;

        $places = $data['places'] ?? [];
        if (empty($places)) return null;

        $imagePool = $this->fetchImages("tourist attractions in {$destination}");

        $out = [];
        foreach ($places as $idx => $p) {
            $name = $p['title'] ?? null;
            if (!$name) continue;

            $imgEntry = $imagePool[$idx] ?? $imagePool[0] ?? null;
            $image    = $imgEntry['thumbnailUrl'] ?? $imgEntry['imageUrl'] ?? null;

            $out[] = [
                'name'    => $name,
                'isFree'  => false,
                'price'   => rand(100, 500),
                'image'   => $image,
                'rating'  => $p['rating']   ?? null,
                'reviews' => $p['ratingCount'] ?? null,
                'address' => $p['address'] ?? $destination,
                'source'  => 'serper',
            ];
        }

        return $out ?: null;
    }
}

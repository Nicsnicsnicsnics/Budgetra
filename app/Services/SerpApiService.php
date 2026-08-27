<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SerpApiService
{
    private string $key;

    // Max SerpAPI calls per day (free plan = 100/month â‰ˆ 3/day; paid plans higher)
    private int $dailyLimit = 80;

    // How long to cache each query type (hours)
    private array $cacheTtl = [
        'google_flights' => 6,
        'google_hotels'  => 12,
        'google_maps'    => 24,
        'google_images'  => 24,
        'default'        => 12,
    ];

    private function cachedRequest(array $params): ?array
    {
        // Strip api_key from hash so key rotation doesn't bust cache
        $hashParams = $params;
        unset($hashParams['api_key']);
        ksort($hashParams);
        $hash = hash('sha256', json_encode($hashParams));

        // 1. Check cache
        $cached = DB::table('serpapi_cache')
            ->where('query_hash', $hash)
            ->where('expires_at', '>', now())
            ->value('response_json');

        if ($cached) {
            return json_decode($cached, true);
        }

        // 2. Check daily usage limit
        $today = now()->toDateString();
        $usage = DB::table('serpapi_usage')->where('usage_date', $today)->value('request_count') ?? 0;
        if ($usage >= $this->dailyLimit) {
            return null; // limit reached, return null â€” callers handle gracefully
        }

        // 3. Make real request
        try {
            $response = Http::timeout(20)->connectTimeout(10)->retry(2, 500, throw: false)->get('https://serpapi.com/search', $params);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);
            return null;
        }

        // 4. Increment usage counter
        DB::table('serpapi_usage')->upsert(
            ['usage_date' => $today, 'request_count' => $usage + 1],
            ['usage_date'],
            ['request_count' => DB::raw('serpapi_usage.request_count + 1')]
        );

        if (!$response->successful()) return null;

        $data = $response->json();

        // 5. Store in cache
        $engine = $params['engine'] ?? 'default';
        $ttlHours = $this->cacheTtl[$engine] ?? $this->cacheTtl['default'];

        DB::table('serpapi_cache')->upsert([
            'query_hash'    => $hash,
            'query_params'  => json_encode($hashParams),
            'response_json' => json_encode($data),
            'created_at'    => now(),
            'expires_at'    => now()->addHours($ttlHours),
        ], ['query_hash'], ['response_json', 'expires_at']);

        return $data;
    }

    // Coordinates for major destinations â€” used for 15km radius searches
    // Format: [lat, lng]
    private array $coords = [
        // Philippine cities
        'manila'           => [14.5995, 120.9842],
        'makati'           => [14.5547, 121.0244],
        'quezon city'      => [14.6760, 121.0437],
        'cebu'             => [10.3157, 123.8854],
        'cebu city'        => [10.3157, 123.8854],
        'davao'            => [7.1907,  125.4553],
        'davao city'       => [7.1907,  125.4553],
        'boracay'          => [11.9674, 121.9248],
        'bohol'            => [9.8500,  124.1435],
        'palawan'          => [9.8349,  118.7384],
        'puerto princesa'  => [9.7392,  118.7353],
        'el nido'          => [11.1784, 119.4074],
        'coron'            => [12.0031, 120.2040],
        'siargao'          => [9.8490,  126.0458],
        'bacolod'          => [10.6765, 122.9509],
        'iloilo'           => [10.7202, 122.5621],
        'iloilo city'      => [10.7202, 122.5621],
        'zamboanga'        => [6.9214,  122.0790],
        'cagayan de oro'   => [8.4542,  124.6319],
        'general santos'   => [6.1164,  125.1716],
        'tagaytay'         => [14.1153, 120.9621],
        'baguio'           => [16.4023, 120.5960],
        'vigan'            => [17.5747, 120.3869],
        'batangas'         => [13.7565, 121.0583],
        'tacloban'         => [11.2543, 124.9900],
        'dumaguete'        => [9.3076,  123.3080],
        'surigao'          => [9.7527,  125.4874],
        'camiguin'         => [9.1737,  124.7197],
        'siquijor'         => [9.2100,  123.5100],
        'batanes'          => [20.4487, 121.9700],
        'sagada'           => [17.0880, 120.9010],
        'pagudpud'         => [18.5600, 120.7900],
        'laoag'            => [18.1977, 120.5937],
        'puerto galera'    => [13.5021, 120.9539],
        // International
        'singapore'        => [1.3521,  103.8198],
        'bangkok'          => [13.7563, 100.5018],
        'phuket'           => [7.8804,  98.3923],
        'bali'             => [-8.3405, 115.0920],
        'kuala lumpur'     => [3.1390,  101.6869],
        'hong kong'        => [22.3193, 114.1694],
        'tokyo'            => [35.6762, 139.6503],
        'osaka'            => [34.6937, 135.5023],
        'seoul'            => [37.5665, 126.9780],
        'taipei'           => [25.0330, 121.5654],
        'dubai'            => [25.2048, 55.2708],
        'london'           => [51.5074, -0.1278],
        'paris'            => [48.8566, 2.3522],
        'new york'         => [40.7128, -74.0060],
        'new york city'    => [40.7128, -74.0060],
        'sydney'           => [-33.8688, 151.2093],
        'rome'             => [41.9028, 12.4964],
        'barcelona'        => [41.3851, 2.1734],
        'amsterdam'        => [52.3676, 4.9041],
        'maldives'         => [3.2028,  73.2207],
    ];

    public function __construct()
    {
        $this->key = config('services.serpapi.key');
    }

    // Returns "@lat,lng,14z" string for 15km radius (~zoom 12-13)
    private function ll(string $destination): ?string
    {
        $key = strtolower(trim($destination));
        if (!isset($this->coords[$key])) return null;
        [$lat, $lng] = $this->coords[$key];
        return "@{$lat},{$lng},13z";  // zoom 13 â‰ˆ 15km radius
    }

    // â”€â”€ Raw flight list for manual selection UI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchFlightsRaw(
        string $fromCode,
        string $toCode,
        string $departDate,
        string $returnDate = ''
    ): ?array {
        $params = [
            'engine'        => 'google_flights',
            'departure_id'  => $fromCode,
            'arrival_id'    => $toCode,
            'outbound_date' => $departDate,
            'currency'      => 'PHP',
            'hl'            => 'en',
            'api_key'       => $this->key,
        ];
        if ($returnDate) {
            $params['return_date'] = $returnDate;
            $params['type']        = '1';
        } else {
            $params['type'] = '2';
        }
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $raw     = array_merge($data['best_flights'] ?? [], $data['other_flights'] ?? []);
        if (empty($raw)) return null;

        $results = [];
        foreach ($raw as $item) {
            $segments = $item['flights'] ?? [];
            if (empty($segments)) continue;

            $first   = $segments[0];
            $last    = $segments[count($segments) - 1];

            $airline = $first['airline'] ?? 'Airline';
            $logo    = $first['airline_logo'] ?? null;
            $num     = $first['flight_number'] ?? '';
            $depart  = $first['departure_airport']['time'] ?? '';
            $arrive  = $last['arrival_airport']['time'] ?? '';
            $dep_id  = $first['departure_airport']['id'] ?? $fromCode;
            $arr_id  = $last['arrival_airport']['id'] ?? $toCode;
            $dur     = $item['total_duration'] ?? ($first['duration'] ?? 0);
            $price   = (int)($item['price'] ?? 0);
            $type    = isset($params['return_date']) ? 'Round Trip' : 'One Way';
            $bags    = 'Carry-on baggage included';

            if (!$price) continue;

            // Google routinely returns connecting itineraries — for thin
            // domestic pairs (CEB→TAG, say) that's ALL it returns. The card
            // used to print a hardcoded "Nonstop" over them, so a two-leg
            // trip with a five-hour Manila layover read as a 7h55m direct
            // flight. Carry the real shape through so the view can tell the
            // truth about it.
            $stops    = max(0, count($segments) - 1);
            $layovers = [];
            foreach ($item['layovers'] ?? [] as $lay) {
                $layovers[] = [
                    'id'       => $lay['id'] ?? '',
                    'duration' => (int) ($lay['duration'] ?? 0),
                ];
            }

            // Every flight number in the itinerary, not just the first leg's.
            $segNumbers = [];
            foreach ($segments as $seg) {
                $n = trim((string) ($seg['flight_number'] ?? ''));
                if ($n !== '') $segNumbers[] = $n;
            }

            // "6:20 AM" on its own is misleading when the leg departed the
            // night before — SerpAPI gives full "Y-m-d H:i" timestamps, so
            // the day difference is knowable.
            $dayOffset = 0;
            if ($depart && $arrive) {
                $d1 = strtotime(substr($depart, 0, 10));
                $d2 = strtotime(substr($arrive, 0, 10));
                if ($d1 && $d2 && $d2 > $d1) {
                    $dayOffset = (int) round(($d2 - $d1) / 86400);
                }
            }

            // Skip results that don't depart from the requested origin
            if ($dep_id && $fromCode && strtoupper(substr($dep_id, 0, 3)) !== strtoupper(substr($fromCode, 0, 3))) {
                continue;
            }

            $results[] = [
                'airline'    => $airline,
                'logo'       => $logo,
                'number'     => $segNumbers ? implode(' · ', $segNumbers) : $num,
                'depart'     => $depart,
                'arrive'     => $arrive,
                'dep_id'     => $dep_id,
                'arr_id'     => $arr_id,
                'duration'   => $dur,
                'price'      => $price,
                'type'       => $type,
                'bags'       => $bags,
                'stops'      => $stops,
                'layovers'   => $layovers,
                'day_offset' => $dayOffset,
                'source'     => 'serpapi',
            ];
        }

        return $results ?: null;
    }

    // â”€â”€ Flights â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchFlights(
        string $fromCode,
        string $toCode,
        string $departDate,
        string $returnDate = '',
        int    $gen = 0,
        int    $budgetCap = 0
    ): ?array {
        $params = [
            'engine'        => 'google_flights',
            'departure_id'  => $fromCode,
            'arrival_id'    => $toCode,
            'outbound_date' => $departDate,
            'currency'      => 'PHP',
            'hl'            => 'en',
            'api_key'       => $this->key,
        ];

        if ($returnDate) {
            $params['return_date'] = $returnDate;
            $params['type']        = '1';
        } else {
            $params['type'] = '2';
        }
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $bestFlights = $data['best_flights'] ?? $data['other_flights'] ?? [];
        if (empty($bestFlights)) return null;

        // Filter out flights exceeding budget cap
        if ($budgetCap > 0) {
            $bestFlights = array_values(array_filter($bestFlights, fn($f) => ($f['price'] ?? 0) <= $budgetCap));
        }
        if (empty($bestFlights)) return null;

        // Sort: gen=0 most expensive within budget (best quality), gen>0 cheaper options
        usort($bestFlights, fn($a, $b) => $gen === 0
            ? ($b['price'] ?? 0) <=> ($a['price'] ?? 0)
            : ($a['price'] ?? 0) <=> ($b['price'] ?? 0)
        );
        // gen=0: top 3 (most expensive that fit), gen>0: slide into cheaper tier
        $offset = $gen === 0 ? 0 : min($gen, max(0, count($bestFlights) - 3));
        $pool   = array_slice($bestFlights, $offset, 3);
        $top    = $pool[array_rand($pool)];
        $flight  = $top['flights'][0] ?? [];
        $airline = trim(($flight['airline'] ?? 'Airline') . ' ' . ($flight['flight_number'] ?? ''));
        $price   = (int)($top['price'] ?? 0);

        return [
            'detail' => $airline . ' · ' . ($returnDate ? 'Round Trip' : 'One Way'),
            'cost'   => $price,
        ];
    }

    // â”€â”€ Raw hotel list for manual accommodation selection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchHotelsRaw(string $destination, string $checkIn, string $checkOut, int $nights, string $type = 'hotel'): ?array
    {
        // Prefix the query with the property type so Google Hotels returns relevant results
        $typeLabel = match($type) {
            'apartment' => 'Apartment',
            'inn'       => 'Inn',
            'resort'    => 'Resort',
            default     => 'Hotel',
        };
        $params = [
            'engine'         => 'google_hotels',
            'q'              => $typeLabel . ' in ' . $destination,
            'check_in_date'  => $checkIn,
            'check_out_date' => $checkOut,
            'currency'       => 'PHP',
            'hl'             => 'en',
            'gl'             => 'ph',
            'num'            => 20,
            'api_key'        => $this->key,
        ];
        $data   = $this->cachedRequest($params);
        $hotels = $data['properties'] ?? [];

        // If no results for exact dates, retry with nearby generic dates (+7/+13 from today)
        if (empty($hotels)) {
            $fallbackIn  = date('Y-m-d', strtotime('+7 days'));
            $fallbackOut = date('Y-m-d', strtotime('+7 days') + $nights * 86400);
            $params2 = array_merge($params, ['check_in_date' => $fallbackIn, 'check_out_date' => $fallbackOut]);
            $data2   = $this->cachedRequest($params2);
            $hotels  = $data2['properties'] ?? [];
        }

        // Still few results — try without type prefix for broader results
        if (count($hotels) < 5) {
            $params3 = array_merge($params, ['q' => $destination]);
            $data3   = $this->cachedRequest($params3);
            if ($data3) {
                $extra    = $data3['properties'] ?? [];
                $existing = array_column($hotels, 'name');
                foreach ($extra as $e) {
                    if (!in_array($e['name'] ?? '', $existing)) {
                        $hotels[]   = $e;
                        $existing[] = $e['name'] ?? '';
                    }
                }
            }
        }

        if (empty($hotels)) return null;

        $results = [];
        foreach ($hotels as $h) {
            $name    = $h['name'] ?? null;
            if (!$name) continue;
            $nightly = (int)preg_replace('/[^\d]/', '', $h['rate_per_night']['lowest'] ?? '0');
            $total   = (int)preg_replace('/[^\d]/', '', $h['total_rate']['lowest']     ?? '0');
            $cost    = $total > 0 ? $total : ($nightly > 0 ? $nightly * $nights : 0);
            if ($cost <= 0) continue;
            $stars   = (int)($h['hotel_class'] ?? $h['stars'] ?? 3);
            $image   = $h['images'][0]['thumbnail'] ?? $h['thumbnail'] ?? null;
            $dist    = $h['nearby_places'][0]['transportations'][0]['duration'] ?? null;
            $rawType = strtolower($h['type'] ?? '');
            if (str_contains($rawType, 'apartment') || str_contains($rawType, 'condo') || str_contains($rawType, 'suite') || str_contains($rawType, 'serviced') || str_contains($rawType, 'residence')) {
                $typeKey = 'apartment';
            } elseif (str_contains($rawType, 'inn') || str_contains($rawType, 'lodge') || str_contains($rawType, 'hostel') || str_contains($rawType, 'guesthouse') || str_contains($rawType, 'guest house') || str_contains($rawType, 'bed') || str_contains($rawType, 'motel') || str_contains($rawType, 'pension')) {
                $typeKey = 'inn';
            } elseif (str_contains($rawType, 'resort')) {
                $typeKey = 'resort';
            } else {
                // Default to whatever type was searched so results always show
                $typeKey = $type;
            }
            $gps = $h['gps_coordinates'] ?? [];
            $results[] = [
                'name'     => $name,
                'stars'    => $stars,
                'image'    => $image,
                'nightly'  => $nightly ?: (int)round($cost / $nights),
                'total'    => $cost,
                'nights'   => $nights,
                'dist'     => $dist,
                'type'     => $typeKey,
                'typeLabel'=> ucfirst($typeKey),
                'lat'      => $gps['latitude']  ?? null,
                'lng'      => $gps['longitude'] ?? null,
            ];
        }
        return $results ?: null;
    }

    // â”€â”€ Hotels â€” within 15km of destination â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchHotels(
        string $destination,
        string $checkIn,
        string $checkOut,
        int    $nights,
        int    $gen = 0,
        int    $budgetCap = 0
    ): ?array {
        $params = [
            'engine'         => 'google_hotels',
            'q'              => $destination,
            'check_in_date'  => $checkIn,
            'check_out_date' => $checkOut,
            'currency'       => 'PHP',
            'hl'             => 'en',
            'gl'             => 'ph',
            'api_key'        => $this->key,
        ];

        // Add coordinates for tighter radius-based results
        $ll = $this->ll($destination);
        if ($ll) $params['ll'] = $ll;
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $hotels = $data['properties'] ?? [];
        if (empty($hotels)) return null;

        // Build valid hotel list with cost, filter by budget cap
        $valid = [];
        foreach ($hotels as $h) {
            $name    = $h['name'] ?? null;
            if (!$name) continue;

            $nightly = (int)preg_replace('/[^\d]/', '', $h['rate_per_night']['lowest'] ?? '0');
            $total   = (int)preg_replace('/[^\d]/', '', $h['total_rate']['lowest']     ?? '0');
            $cost    = $total > 0 ? $total : ($nightly > 0 ? $nightly * $nights : 0);
            if ($cost <= 0) continue;
            if ($budgetCap > 0 && $cost > $budgetCap) continue; // skip over budget

            $stars = (int)($h['hotel_class'] ?? $h['stars'] ?? 3);
            $highlights = $h['room_highlights'] ?? [];
            $type = '';
            foreach ($highlights as $hl) {
                if (is_string($hl) && strlen($hl) > 3) { $type = $hl; break; }
                if (is_array($hl))  { $type = $hl['highlighted_text'] ?? $hl[0] ?? ''; break; }
            }
            $type = $type ?: 'Standard Room';

            $valid[] = [
                'name'   => $name,
                'stars'  => $stars,
                'detail' => $nights . ' Nights · ' . $type . ' · ' . $destination,
                'cost'   => $cost,
            ];
        }

        if (empty($valid)) return null;

        // gen=0: sort most expensive first (best quality within budget)
        // gen>0: sort cheapest first (budget-friendly alternatives)
        usort($valid, fn($a, $b) => $gen === 0
            ? $b['cost'] <=> $a['cost']
            : $a['cost'] <=> $b['cost']
        );
        $offset = $gen === 0 ? 0 : min($gen, max(0, count($valid) - 3));
        $pool   = array_slice($valid, $offset, 3);
        return $pool[array_rand($pool)];
    }

    // â”€â”€ Food & Dining â€” raw list for manual venue selection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchRestaurantsRaw(string $destination, string $category = ''): ?array
    {
        $q = $category && $category !== 'All Cuisines'
            ? $category . ' restaurants near ' . $destination
            : 'best restaurants near ' . $destination;

        $params = [
            'engine'  => 'google_maps',
            'q'       => $q,
            'type'    => 'search',
            'hl'      => 'en',
            'api_key' => $this->key,
        ];

        $key = strtolower(trim($destination));
        if (isset($this->coords[$key])) {
            [$lat, $lng] = $this->coords[$key];
            $params['ll'] = "@{$lat},{$lng},14z";
        }
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $results = $data['local_results'] ?? [];
        if (empty($results)) return null;

        // Post-filter: keep only results whose address mentions the destination
        $destWords = array_filter(explode(' ', strtolower($destination)), fn($w) => strlen($w) > 2);
        $filtered = array_filter($results, function($r) use ($destWords) {
            $addr = strtolower($r['address'] ?? '');
            foreach ($destWords as $w) {
                if (str_contains($addr, $w)) return true;
            }
            return false;
        });
        // Only apply filter if it still leaves results; otherwise use all
        if (!empty($filtered)) $results = array_values($filtered);

        $out = [];
        foreach ($results as $r) {
            $name = $r['title'] ?? null;
            if (!$name) continue;
            $address = $r['address'] ?? '';
            $parts   = array_values(array_filter(array_map('trim', explode(',', $address))));
            $city    = $destination;
            $skip    = ['philippines','thailand','singapore','malaysia','indonesia','japan','korea','taiwan','australia','usa','uk','uae'];
            foreach (array_reverse($parts) as $p) {
                if (!in_array(strtolower($p), $skip) && strlen($p) > 2) { $city = $p; break; }
            }
            $priceRaw = $r['price'] ?? '';
            $priceMin = str_repeat('₱', max(1, substr_count($priceRaw, '₱'))) === '₱' ? 300 : 600;
            $priceMax = $priceMin * 3;

            $gps = $r['gps_coordinates'] ?? [];
            $out[] = [
                'name'     => $name,
                'cuisine'  => $r['type'] ?? ($category ?: 'Restaurant'),
                'city'     => $city,
                'rating'   => $r['rating']   ?? null,
                'reviews'  => $r['reviews']  ?? null,
                'image'    => $r['thumbnail'] ?? null,
                'priceMin' => $priceMin,
                'priceMax' => $priceMax,
                'priceTag' => $priceRaw ?: '₱₱',
                'lat'      => $gps['latitude']  ?? null,
                'lng'      => $gps['longitude'] ?? null,
            ];
        }
        return $out ?: null;
    }

    // â”€â”€ Food & Dining â€” top-rated within 15km â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchRestaurants(string $destination, int $days, int $budgetTotal, int $gen = 0): ?array
    {
        $params = [
            'engine'  => 'google_maps',
            'q'       => 'best restaurants ' . $destination,
            'type'    => 'search',
            'hl'      => 'en',
            'api_key' => $this->key,
        ];

        $ll = $this->ll($destination);
        if ($ll) $params['ll'] = $ll;
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $results = $data['local_results'] ?? [];
        if (empty($results)) return null;

        // First gen: highest-rated (premium). Regenerate: rotate through mid-tier options.
        usort($results, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));
        $offset = $gen === 0 ? 0 : min($gen, count($results) - 5);
        $pool   = array_slice($results, max(0, $offset), 5);
        $top    = $pool[array_rand($pool)];

        $name    = $top['title'] ?? 'Local Restaurant';
        $address = $top['address'] ?? '';
        // Extract city: use second-to-last segment, skip country-level tail
        $parts = array_filter(array_map('trim', explode(',', $address)));
        $parts = array_values($parts);
        $skip  = ['philippines', 'thailand', 'singapore', 'malaysia', 'indonesia', 'japan', 'korea', 'taiwan', 'australia', 'usa', 'uk', 'uae'];
        $city  = $destination; // default
        foreach (array_reverse($parts) as $part) {
            if (!in_array(strtolower($part), $skip) && strlen($part) > 2) {
                $city = $part; break;
            }
        }

        // gen=0: full food budget. gen>0: reduce by 15% per regeneration (cheaper dining)
        $actualBudget = $gen === 0
            ? $budgetTotal
            : (int)round($budgetTotal * max(0.5, 1 - ($gen * 0.15)));
        $perDay = (int)round($actualBudget / $days);

        return [
            'name'   => $name,
            'detail' => $days . ' Days · Breakfast, Lunch, & Dinner · ' . $city,
            'cost'   => $actualBudget,
            'perDay' => $perDay,
        ];
    }

    // â”€â”€ Attractions â€” raw list for manual selection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function currencyFor(string $destination): string
    {
        $dest = strtolower(trim($destination));
        $currencies = [
            // Singapore
            'singapore' => 'SGD',
            // Malaysia
            'kuala lumpur' => 'MYR', 'kl' => 'MYR', 'penang' => 'MYR', 'langkawi' => 'MYR',
            'kota kinabalu' => 'MYR', 'malacca' => 'MYR', 'johor bahru' => 'MYR',
            // Thailand
            'bangkok' => 'THB', 'phuket' => 'THB', 'chiang mai' => 'THB', 'pattaya' => 'THB',
            'krabi' => 'THB', 'koh samui' => 'THB', 'hua hin' => 'THB',
            // Japan
            'tokyo' => 'JPY', 'osaka' => 'JPY', 'kyoto' => 'JPY', 'hiroshima' => 'JPY',
            'nara' => 'JPY', 'fukuoka' => 'JPY', 'sapporo' => 'JPY',
            // South Korea
            'seoul' => 'KRW', 'busan' => 'KRW', 'jeju' => 'KRW',
            // Vietnam
            'hanoi' => 'VND', 'ho chi minh' => 'VND', 'saigon' => 'VND', 'da nang' => 'VND',
            'hoi an' => 'VND', 'nha trang' => 'VND', 'ha long' => 'VND',
            // Indonesia
            'bali' => 'IDR', 'jakarta' => 'IDR', 'yogyakarta' => 'IDR', 'lombok' => 'IDR',
            // Cambodia
            'siem reap' => 'USD', 'phnom penh' => 'USD', 'angkor' => 'USD',
            // UAE
            'dubai' => 'AED', 'abu dhabi' => 'AED',
            // Taiwan
            'taipei' => 'TWD',
            // Hong Kong
            'hong kong' => 'HKD',
            // Australia
            'sydney' => 'AUD', 'melbourne' => 'AUD', 'brisbane' => 'AUD',
            // USA
            'new york' => 'USD', 'los angeles' => 'USD', 'las vegas' => 'USD',
        ];
        foreach ($currencies as $key => $code) {
            if (str_contains($dest, $key)) return $code;
        }
        return 'PHP'; // default to Philippine peso
    }

    private function fmtPrice(int $amount, string $currency): string
    {
        $symbols = [
            'PHP' => '₱', 'SGD' => 'S$', 'MYR' => 'RM', 'THB' => '฿',
            'JPY' => '¥', 'KRW' => '₩', 'VND' => '₫', 'IDR' => 'Rp',
            'USD' => '$', 'AED' => 'AED ', 'TWD' => 'NT$', 'HKD' => 'HK$',
            'AUD' => 'A$',
        ];
        $sym = $symbols[$currency] ?? $currency . ' ';
        // For currencies with very large denominations show approx
        if ($currency === 'VND') $amount *= 500;
        if ($currency === 'IDR') $amount *= 250;
        if ($currency === 'JPY') $amount *= 2;
        if ($currency === 'KRW') $amount *= 50;
        return $sym . number_format($amount);
    }

    public function searchAttractionsRaw(string $destination, string $type = 'All Attractions'): ?array
    {
        // Always embed destination in query so Maps constrains results to that city
        $q = $type && $type !== 'All Attractions'
            ? $type . ' near ' . $destination
            : 'tourist attractions things to do near ' . $destination;

        $params = [
            'engine'  => 'google_maps',
            'q'       => $q,
            'type'    => 'search',
            'hl'      => 'en',
            'api_key' => $this->key,
        ];

        // ll param: zoom 14z â‰ˆ 8km radius; ensures results stay near destination
        $key = strtolower(trim($destination));
        if (isset($this->coords[$key])) {
            [$lat, $lng] = $this->coords[$key];
            $params['ll'] = "@{$lat},{$lng},14z";
        }
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $results = $data['local_results'] ?? [];
        if (empty($results)) return null;

        // Post-filter: drop results whose address doesn't mention the destination city/region
        $destWords = array_filter(explode(' ', strtolower($destination)), fn($w) => strlen($w) > 2);
        $results = array_filter($results, function($r) use ($destWords) {
            $addr = strtolower($r['address'] ?? '');
            foreach ($destWords as $w) {
                if (str_contains($addr, $w)) return true;
            }
            return false;
        });
        if (empty($results)) return null;

        usort($results, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        // Known entrance fees (lowercase partial match against attraction title)
        $feeMap = [
            // Cebu
            "magellan's cross"              => 'FREE',
            'fort san pedro'                => '₱30',
            'temple of leah'                => '₱150',
            'tops lookout'                  => '₱60',
            'cebu taoist temple'            => 'FREE',
            'yap-sandiego'                  => '₱50',
            'museo sugbo'                   => '₱50',
            'santo niÃ±o'                    => 'FREE',
            'basilica minore'               => 'FREE',
            'sirao garden'                  => '₱50',
            'kawasan falls'                 => '₱50',
            'osmeÃ±a peak'                   => 'FREE',
            'bantayan'                      => 'FREE',
            'sumilon'                       => '₱150',
            'moalboal'                      => 'FREE',
            'oslob whale'                   => '₱500',
            'whale shark'                   => '₱500',
            'simala'                        => 'FREE',
            'tops cebu'                     => '₱60',
            // Bohol
            'chocolate hills'               => '₱50',
            'tarsier sanctuary'             => '₱100',
            'tarsier conservation'          => '₱100',
            'philippine tarsier'            => '₱100',
            'loboc river'                   => '₱500',
            'blood compact shrine'          => '₱30',
            'baclayon church'               => 'FREE',
            'hinagdanan cave'               => '₱80',
            'danao adventure'               => '₱200',
            'chocolate hills adventure'     => '₱150',
            'bohol enchanted'               => '₱200',
            'zoocolate'                     => '₱200',
            'loboc ecotourism'              => '₱150',
            'alona beach'                   => 'FREE',
            'panglao beach'                 => 'FREE',
            'balicasag'                     => '₱150',
            'virgin island bohol'           => '₱50',
            // Manila / Metro
            'intramuros'                    => 'FREE',
            'rizal park'                    => 'FREE',
            'national museum of fine arts'  => 'FREE',
            'national museum of natural'    => 'FREE',
            'national museum of'            => 'FREE',
            'fort santiago'                 => '₱75',
            'manila ocean park'             => '₱350',
            'sky ranch'                     => '₱200',
            'picnic grove'                  => '₱50',
            'pinto art museum'              => '₱200',
            'bgc arts center'               => 'FREE',
            'bonifacio global'              => 'FREE',
            'nayong pilipino'               => '₱100',
            'star city'                     => '₱500',
            'enchanted kingdom'             => '₱750',
            'taal volcano'                  => '₱1,000',
            'taal heritage town'            => 'FREE',
            'las casas filipinas'           => '₱200',
            'corregidor'                    => '₱1,500',
            // Boracay
            'white beach'                   => 'FREE',
            "willy's rock"                  => 'FREE',
            'mt luho'                       => '₱50',
            'ariel\'s point'                => '₱2,500',
            'puka shell beach'              => 'FREE',
            // Palawan / El Nido
            'underground river'             => '₱150',
            'honda bay'                     => '₱500',
            'el nido tour'                  => '₱1,200',
            'twin lagoon'                   => '₱100',
            'kayangan lake'                 => '₱200',
            'maquinit'                      => '₱200',
            'nacpan beach'                  => '₱100',
            // Davao
            'philippine eagle center'       => '₱200',
            'eden nature park'              => '₱150',
            'crocodile park'                => '₱250',
            'people\'s park davao'          => 'FREE',
            'malagos garden'                => '₱100',
            // Siargao
            'cloud 9'                       => 'FREE',
            'sugba lagoon'                  => '₱150',
            'magpupungko'                   => '₱50',
            'guyam island'                  => '₱150',
            // Vigan
            'calle crisologo'               => 'FREE',
            'bantay bell tower'             => 'FREE',
            'pagburnayan'                   => 'FREE',
            // Batangas
            'anilao'                        => '₱500',
            'fortune island'                => '₱300',
            // General
            'national park'                 => '₱50',
            // International
            'universal studios singapore'   => '₱3,500',
            'gardens by the bay'            => 'FREE',
            'marina bay sands skypark'      => '₱1,200',
            'petronas twin towers'          => '₱1,000',
            'batu caves'                    => 'FREE',
            'wat phra kaew'                 => '₱1,500',
            'wat pho'                       => '₱600',
            'grand palace'                  => '₱2,000',
            'angkor wat'                    => '₱2,500',
            'tokyo skytree'                 => '₱1,800',
            'senso-ji'                      => 'FREE',
            'meiji shrine'                  => 'FREE',
            'fushimi inari'                 => 'FREE',
            'disneyland'                    => '₱4,000',
            'gyeongbokgung'                 => '₱500',
            'n seoul tower'                 => '₱800',
            'burj khalifa'                  => '₱2,500',
            'dubai mall'                    => 'FREE',
            'ha long bay'                   => '₱1,500',
        ];

        $out = [];
        foreach ($results as $r) {
            $name = $r['title'] ?? null;
            if (!$name) continue;
            $address = $r['address'] ?? '';
            $parts   = array_values(array_filter(array_map('trim', explode(',', $address))));
            $city    = $destination;
            $skip    = ['philippines','thailand','singapore','malaysia','indonesia','japan','korea','taiwan','australia','usa','uk','uae'];
            foreach (array_reverse($parts) as $p) {
                if (!in_array(strtolower($p), $skip) && strlen($p) > 2) { $city = $p; break; }
            }

            // 1. Match against known fee map
            $nameLower = strtolower($name);
            $currency  = $this->currencyFor($destination);
            $fee = null;
            foreach ($feeMap as $key => $val) {
                if (str_contains($nameLower, $key)) {
                    if ($val !== 'FREE' && $currency !== 'PHP') {
                        // feeMap stores PHP-equivalent amounts; convert symbol for international
                        $amount = (int) preg_replace('/[^\d]/', '', $val);
                        $fee = $amount > 0 ? $this->fmtPrice($amount, $currency) : 'FREE';
                    } else {
                        $fee = $val;
                    }
                    break;
                }
            }

            // 2. Fallback: use SerpAPI type field (always populated, reliable)
            if ($fee === null) {
                $typeLower = strtolower($r['type'] ?? '');
                $fee = match(true) {
                    str_contains($typeLower, 'national museum') || $typeLower === 'museum' && str_contains($nameLower, 'national') => 'FREE',
                    str_contains($typeLower, 'museum')                                    => $this->fmtPrice(50, $currency),
                    str_contains($typeLower, 'amusement park') || str_contains($typeLower, 'theme park') || str_contains($typeLower, 'water park') => $this->fmtPrice(300, $currency),
                    str_contains($typeLower, 'aquarium')                                  => $this->fmtPrice(350, $currency),
                    str_contains($typeLower, 'zoo') || str_contains($typeLower, 'wildlife') => $this->fmtPrice(200, $currency),
                    str_contains($typeLower, 'beach')                                     => 'FREE',
                    str_contains($typeLower, 'church') || str_contains($typeLower, 'cathedral') || str_contains($typeLower, 'shrine') => 'FREE',
                    str_contains($typeLower, 'city park') || str_contains($typeLower, 'park')    => 'FREE',
                    str_contains($typeLower, 'hiking')                                    => 'FREE',
                    str_contains($typeLower, 'garden')                                    => 'FREE',
                    str_contains($typeLower, 'resort')                                    => $this->fmtPrice(150, $currency),
                    str_contains($typeLower, 'observatory') || str_contains($typeLower, 'tower') => $this->fmtPrice(200, $currency),
                    // name-based fallbacks for "Tourist attraction" type (most common)
                    str_contains($nameLower, 'falls') || str_contains($nameLower, 'waterfall') => $this->fmtPrice(50, $currency),
                    str_contains($nameLower, 'cave')                                           => $this->fmtPrice(80, $currency),
                    str_contains($nameLower, 'fort') || str_contains($nameLower, 'ruins')      => $this->fmtPrice(75, $currency),
                    str_contains($nameLower, 'shrine') || str_contains($nameLower, 'monument') => 'FREE',
                    str_contains($nameLower, 'beach') || str_contains($nameLower, 'island')    => 'FREE',
                    str_contains($nameLower, 'church') || str_contains($nameLower, 'cathedral')=> 'FREE',
                    str_contains($nameLower, 'museum')                                         => $this->fmtPrice(50, $currency),
                    str_contains($nameLower, 'eco park') || str_contains($nameLower, 'nature') => $this->fmtPrice(50, $currency),
                    default => null,
                };
            }

            $isFree = $fee === 'FREE' || strtolower((string)$fee) === 'free';

            $out[] = [
                'name'    => $name,
                'type'    => $r['type'] ?? 'Attraction',
                'city'    => $city,
                'rating'  => $r['rating']   ?? null,
                'reviews' => $r['reviews']  ?? null,
                'image'   => $r['thumbnail'] ?? null,
                'price'   => $isFree ? 'FREE' : $fee,
                'isFree'  => $isFree,
            ];
        }
        return $out ?: null;
    }

    // â”€â”€ Attractions â€” google_maps â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function searchAttractions(string $destination, int $gen = 0): ?array
    {
        $params = [
            'engine'  => 'google_maps',
            'q'       => 'tourist attractions things to do in ' . $destination,
            'type'    => 'search',
            'hl'      => 'en',
            'api_key' => $this->key,
        ];

        $ll = $this->ll($destination);
        if ($ll) $params['ll'] = $ll;
        $data = $this->cachedRequest($params);
        if (!$data) return null;
        // data already decoded
        $results = $data['local_results'] ?? [];
        if (empty($results)) return null;

        usort($results, fn($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        // First gen: top attractions. Regenerate: slide window down the list.
        $offset = $gen === 0 ? 0 : min($gen * 3, max(0, count($results) - 6));
        $pool   = array_slice($results, $offset, 8);
        shuffle($pool);
        $items = [];
        foreach (array_slice($pool, 0, 3) as $r) {
            $title = $r['title'] ?? null;
            if (!$title) continue;
            $items[] = [$title, 'Free'];
        }
        return $items ?: null;
    }

    // ── Place photo — google_images. Shared by destinations (context =
    // country) and attractions (context = destination city); $hint biases
    // the search toward the right kind of photo for each. ────────────────
    public function searchPlaceImage(string $name, ?string $context = null, string $hint = 'travel destination'): ?string
    {
        $params = [
            'engine'  => 'google_images',
            'q'       => trim($name . ' ' . $context . ' ' . $hint),
            'hl'      => 'en',
            // Bias toward large photos instead of accepting whatever the
            // first result happens to be — the un-filtered default search
            // frequently surfaced small/blurry thumbnails.
            'tbs'     => 'isz:l',
            'api_key' => $this->key,
        ];

        $data = $this->cachedRequest($params);
        if (!$data) return null;

        $results = $data['images_results'] ?? [];
        if (!$results) return null;

        // Among the top results, pick the highest-resolution image rather
        // than just the first one — the first result isn't always the
        // sharpest, and original_width/height let us rank for clarity.
        $best     = null;
        $bestArea = 0;
        foreach (array_slice($results, 0, 8) as $r) {
            $url = $r['original'] ?? $r['thumbnail'] ?? null;
            if (!$url) continue;
            $area = (int) ($r['original_width'] ?? 0) * (int) ($r['original_height'] ?? 0);
            if ($area > $bestArea) {
                $bestArea = $area;
                $best     = $url;
            }
            if ($best === null) $best = $url;
        }

        return $best;
    }
}

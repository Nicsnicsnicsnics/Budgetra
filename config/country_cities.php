<?php

// City suggestions for the Profile Builder's "Where does your journey
// begin?" step, keyed by the exact country names offered in the
// registration form's Country dropdown (resources/views/auth/register.blade.php).
// Each entry is [3-letter IATA code => city name], first entry doubling as
// the default "suggested" pick.
return [
    'Philippines' => [
        'MNL' => 'Manila', 'CEB' => 'Cebu City', 'DVO' => 'Davao City',
        'KLO' => 'Boracay', 'PPS' => 'Puerto Princesa', 'TAG' => 'Tagbilaran',
        'ILO' => 'Iloilo City', 'BCD' => 'Bacolod City', 'GES' => 'General Santos',
        'ZAM' => 'Zamboanga City', 'TAC' => 'Tacloban City', 'IAO' => 'Siargao',
    ],
    'Indonesia' => [
        'CGK' => 'Jakarta', 'DPS' => 'Bali', 'SUB' => 'Surabaya',
    ],
    'Thailand' => [
        'BKK' => 'Bangkok', 'HKT' => 'Phuket', 'CNX' => 'Chiang Mai',
    ],
    'Vietnam' => [
        'SGN' => 'Ho Chi Minh City', 'HAN' => 'Hanoi', 'DAD' => 'Da Nang',
    ],
    'Malaysia' => [
        'KUL' => 'Kuala Lumpur', 'PEN' => 'Penang', 'BKI' => 'Kota Kinabalu',
    ],
    'Singapore' => [
        'SIN' => 'Singapore',
    ],
    'Japan' => [
        'NRT' => 'Tokyo', 'KIX' => 'Osaka', 'CTS' => 'Sapporo',
    ],
    'South Korea' => [
        'ICN' => 'Seoul', 'PUS' => 'Busan',
    ],
    'China' => [
        'PEK' => 'Beijing', 'PVG' => 'Shanghai', 'CAN' => 'Guangzhou',
    ],
    'India' => [
        'DEL' => 'Delhi', 'BOM' => 'Mumbai', 'BLR' => 'Bangalore',
    ],
    'Australia' => [
        'SYD' => 'Sydney', 'MEL' => 'Melbourne', 'BNE' => 'Brisbane',
    ],
    'New Zealand' => [
        'AKL' => 'Auckland', 'WLG' => 'Wellington',
    ],
    'United States' => [
        'JFK' => 'New York', 'LAX' => 'Los Angeles', 'ORD' => 'Chicago',
    ],
    'Canada' => [
        'YYZ' => 'Toronto', 'YVR' => 'Vancouver', 'YUL' => 'Montreal',
    ],
    'United Kingdom' => [
        'LHR' => 'London', 'MAN' => 'Manchester',
    ],
    'Germany' => [
        'FRA' => 'Frankfurt', 'BER' => 'Berlin', 'MUC' => 'Munich',
    ],
    'France' => [
        'CDG' => 'Paris', 'NCE' => 'Nice',
    ],
    'Italy' => [
        'FCO' => 'Rome', 'MXP' => 'Milan',
    ],
    'Spain' => [
        'MAD' => 'Madrid', 'BCN' => 'Barcelona',
    ],
    'Netherlands' => [
        'AMS' => 'Amsterdam',
    ],
    'Brazil' => [
        'GRU' => 'Sao Paulo', 'GIG' => 'Rio de Janeiro',
    ],
    'Mexico' => [
        'MEX' => 'Mexico City', 'CUN' => 'Cancun',
    ],
    'Argentina' => [
        'EZE' => 'Buenos Aires',
    ],
    'Saudi Arabia' => [
        'RUH' => 'Riyadh', 'JED' => 'Jeddah',
    ],
    'United Arab Emirates' => [
        'DXB' => 'Dubai', 'AUH' => 'Abu Dhabi',
    ],
    'Egypt' => [
        'CAI' => 'Cairo',
    ],
    'Nigeria' => [
        'LOS' => 'Lagos', 'ABV' => 'Abuja',
    ],
    'South Africa' => [
        'JNB' => 'Johannesburg', 'CPT' => 'Cape Town',
    ],
    'Kenya' => [
        'NBO' => 'Nairobi',
    ],
];

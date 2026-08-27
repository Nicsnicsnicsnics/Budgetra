<?php
namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationPlacesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Philippines' => [
                'Batanes', 'Batangas', 'Bohol', 'Boracay', 'Camiguin', 'Cebu City',
                'Coron, Palawan', 'Davao City', 'Dumaguete', 'El Nido, Palawan',
                'General Luna, Siargao', 'Iloilo City', 'La Union', 'Legazpi, Bicol',
                'Malapascua Island', 'Naga City', 'Pagudpud', 'Puerto Princesa',
                'Sagada', 'Siargao Island', 'Tagaytay', 'Vigan, Ilocos Sur',
                'Zamboanga City',
            ],
            'Indonesia' => [
                'Bali', 'Bandung', 'Batam', 'Belitung', 'Bintan', 'Flores',
                'Gili Islands', 'Jakarta', 'Yogyakarta', 'Komodo Island',
                'Labuan Bajo', 'Lombok', 'Makassar', 'Malang', 'Medan',
                'Raja Ampat', 'Surabaya', 'Toraja', 'Wakatobi',
            ],
            'Thailand' => [
                'Bangkok', 'Chiang Mai', 'Chiang Rai', 'Hua Hin', 'Kanchanaburi',
                'Koh Kood', 'Koh Lanta', 'Koh Phangan', 'Koh Samui', 'Koh Tao',
                'Krabi', 'Pai', 'Pattaya', 'Phuket', 'Sukhothai',
            ],
            'Vietnam' => [
                'Da Lat', 'Da Nang', 'Ha Long Bay', 'Hanoi', 'Ho Chi Minh City',
                'Hoi An', 'Hue', 'Nha Trang', 'Phu Quoc', 'Quy Nhon', 'Sa Pa',
            ],
            'Malaysia' => [
                'Cameron Highlands', 'George Town, Penang', 'Ipoh', 'Johor Bahru',
                'Kota Kinabalu', 'Kuala Lumpur', 'Kuching, Sarawak',
                'Langkawi', 'Malacca', 'Tioman Island',
            ],
            'Singapore' => [
                'Clarke Quay', 'Gardens by the Bay', 'Little India',
                'Marina Bay Sands', 'Orchard Road', 'Sentosa Island',
            ],
            'Japan' => [
                'Fukuoka', 'Hakone', 'Hiroshima', 'Hokkaido (Sapporo)', 'Kamakura',
                'Kanazawa', 'Kyoto', 'Miyajima', 'Nagasaki', 'Nagoya',
                'Nara', 'Nikko', 'Okinawa', 'Osaka', 'Tokyo',
            ],
            'South Korea' => [
                'Busan', 'Gyeongju', 'Incheon', 'Jeju Island', 'Seoul',
            ],
            'China' => [
                'Beijing', 'Chengdu', 'Guilin', 'Hangzhou', 'Lhasa',
                'Shanghai', 'Shenzhen', "Xi'an", 'Zhangjiajie',
            ],
            'India' => [
                'Agra', 'Bangalore', 'Chennai', 'Delhi', 'Goa',
                'Jaipur', 'Kolkata', 'Mumbai', 'Mysore', 'Rishikesh',
                'Udaipur', 'Varanasi',
            ],
            'Australia' => [
                'Adelaide', 'Brisbane', 'Cairns', 'Darwin', 'Gold Coast',
                'Melbourne', 'Perth', 'Sydney', 'Uluru',
            ],
            'New Zealand' => [
                'Auckland', 'Christchurch', 'Dunedin', 'Queenstown',
                'Rotorua', 'Taupo', 'Wellington',
            ],
            'United States' => [
                'Boston', 'Chicago', 'Honolulu', 'Las Vegas', 'Los Angeles',
                'Miami', 'Nashville', 'New Orleans', 'New York City',
                'Portland', 'San Francisco', 'Seattle', 'Washington D.C.',
            ],
            'Canada' => [
                'Banff', 'Calgary', 'Montreal', 'Ottawa', 'Quebec City',
                'Toronto', 'Vancouver', 'Victoria', 'Whistler',
            ],
            'United Kingdom' => [
                'Bath', 'Birmingham', 'Brighton', 'Cambridge', 'Edinburgh',
                'Glasgow', 'Liverpool', 'London', 'Manchester', 'Oxford', 'York',
            ],
            'France' => [
                'Bordeaux', 'Chamonix', 'Lyon', 'Marseille', 'Mont Saint-Michel',
                'Nice', 'Paris', 'Strasbourg', 'Toulouse',
            ],
            'Germany' => [
                'Berlin', 'Cologne', 'Dresden', 'Frankfurt', 'Hamburg',
                'Heidelberg', 'Munich', 'Nuremberg',
            ],
            'Italy' => [
                'Amalfi Coast', 'Bologna', 'Cinque Terre', 'Florence',
                'Lake Como', 'Milan', 'Naples', 'Rome', 'Sicily',
                'Turin', 'Venice', 'Verona',
            ],
            'Spain' => [
                'Barcelona', 'Bilbao', 'Granada', 'Ibiza', 'Madrid',
                'Malaga', 'Seville', 'Valencia',
            ],
            'Greece' => [
                'Athens', 'Corfu', 'Crete', 'Mykonos', 'Rhodes',
                'Santorini', 'Thessaloniki',
            ],
            'Turkey' => [
                'Antalya', 'Bodrum', 'Cappadocia', 'Ephesus', 'Istanbul',
                'Izmir', 'Pamukkale',
            ],
            'Brazil' => [
                'Bahia', 'Bonito', 'Fernando de Noronha', 'Florianopolis',
                'Fortaleza', 'Foz do Iguazu', 'Manaus',
                'Natal', 'Recife', 'Rio de Janeiro', 'Sao Paulo',
            ],
            'Mexico' => [
                'Cancun', 'Guadalajara', 'Guanajuato', 'Mexico City',
                'Oaxaca', 'Puerto Vallarta', 'Tulum',
            ],
            'South Africa' => [
                'Cape Town', 'Durban', 'Garden Route', 'Johannesburg',
                'Kruger National Park', 'Pretoria', 'Stellenbosch',
            ],
            'Egypt' => [
                'Alexandria', 'Aswan', 'Cairo', 'Dahab', 'Hurghada',
                'Luxor', 'Sharm El-Sheikh',
            ],
            'UAE' => [
                'Abu Dhabi', 'Dubai', 'Fujairah', 'Sharjah',
            ],
        ];

        foreach ($data as $country => $cities) {
            foreach ($cities as $city) {
                Destination::firstOrCreate(
                    ['name' => $city, 'country' => $country],
                    ['region' => $country === 'Philippines' ? 'local' : 'international', 'description' => null, 'image' => null]
                );
            }
        }
    }
}

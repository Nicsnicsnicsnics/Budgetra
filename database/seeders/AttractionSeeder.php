<?php
namespace Database\Seeders;

use App\Models\Attraction;
use Illuminate\Database\Seeder;

class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $attractions = [
            // Cebu City
            ['destination' => 'Cebu City', 'name' => "Magellan's Cross", 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A Christian cross planted by Portuguese and Spanish explorers as ordered by Ferdinand Magellan upon arriving in Cebu in 1521.'],
            ['destination' => 'Cebu City', 'name' => 'Temple of Leah', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'The "Taj Mahal of Cebu" — a grand hilltop temple built as a symbol of undying love, with panoramic city views.'],
            ['destination' => 'Cebu City', 'name' => 'Basilica del Santo Niño', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'The oldest Roman Catholic church in the Philippines, home to the revered Santo Niño de Cebú image.'],
            ['destination' => 'Cebu City', 'name' => 'Tops Lookout', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A mountaintop viewing deck offering sweeping sunset views over Cebu City and the strait beyond.'],

            // Boracay
            ['destination' => 'Boracay', 'name' => 'White Beach', 'category' => 'Nature', 'rating' => 4.7,
                'description' => "Boracay's world-famous stretch of powdery white sand, lined with resorts, bars, and water activities."],
            ['destination' => 'Boracay', 'name' => 'Puka Shell Beach', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A quieter, less-crowded beach on the island known for its puka shells and laid-back atmosphere.'],
            ['destination' => 'Boracay', 'name' => 'Mount Luho', 'category' => 'Adventure', 'rating' => 4.2,
                'description' => "The highest point on Boracay Island, reachable by ATV or cable car, with a 360-degree viewing deck."],

            // Davao City
            ['destination' => 'Davao City', 'name' => 'Philippine Eagle Center', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A wildlife conservation center dedicated to breeding and protecting the critically endangered Philippine eagle.'],
            ['destination' => 'Davao City', 'name' => "People's Park", 'category' => 'Culture', 'rating' => 4.3,
                'description' => "An urban park featuring sculptures and exhibits celebrating Mindanao's diverse indigenous cultures."],
            ['destination' => 'Davao City', 'name' => 'Eden Nature Park', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A mountain resort and garden park offering cool climate, zip-lines, and views over Davao Gulf.'],

            // Dumaguete
            ['destination' => 'Dumaguete', 'name' => 'Rizal Boulevard', 'category' => 'Culture', 'rating' => 4.5,
                'description' => "A historic tree-lined waterfront promenade and the social heart of Dumaguete's city center."],
            ['destination' => 'Dumaguete', 'name' => 'Apo Island', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'A marine sanctuary famed for snorkeling and diving with sea turtles amid protected coral reefs.'],
            ['destination' => 'Dumaguete', 'name' => 'Casaroro Falls', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A scenic waterfall in nearby Valencia reached via a steep staircase through lush forest.'],

            // Iloilo City
            ['destination' => 'Iloilo City', 'name' => 'Miagao Church', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A UNESCO World Heritage baroque church known for its ornate façade blending European and local motifs.'],
            ['destination' => 'Iloilo City', 'name' => 'Molo Church', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A Gothic-Renaissance church nicknamed the "Feminist Church" for its all-female saint statues.'],
            ['destination' => 'Iloilo City', 'name' => 'Iloilo River Esplanade', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A riverside promenade popular for walking, jogging, and evening views along the Iloilo River.'],

            // Manila
            ['destination' => 'Manila', 'name' => 'Intramuros', 'category' => 'Culture', 'rating' => 4.5,
                'description' => "Manila's historic walled city from the Spanish colonial era, home to Fort Santiago and San Agustin Church."],
            ['destination' => 'Manila', 'name' => 'Rizal Park', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A sprawling historic urban park honoring national hero José Rizal, near Manila Bay.'],
            ['destination' => 'Manila', 'name' => 'National Museum of the Philippines', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A leading art and natural history museum showcasing Filipino heritage and archaeological finds.'],

            // Cagayan de Oro
            ['destination' => 'Cagayan de Oro', 'name' => 'CDO River', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'The Cagayan de Oro River, famous nationwide for its whitewater rafting rapids.'],
            ['destination' => 'Cagayan de Oro', 'name' => 'Gardens of Malasag Eco-Tourism Village', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A mountain eco-park with native huts, gardens, and views over Macajalar Bay.'],
            ['destination' => 'Cagayan de Oro', 'name' => 'Divine Mercy Shrine', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A hilltop religious shrine and pilgrimage site overlooking the city.'],

            // Siquijor
            ['destination' => 'Siquijor', 'name' => 'Cambugahay Falls', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'A multi-tiered turquoise waterfall with natural rope swings, one of the island\'s top draws.'],
            ['destination' => 'Siquijor', 'name' => 'Salagdoong Beach', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'A beach known for its clear water and cliff-diving platforms into the sea below.'],
            ['destination' => 'Siquijor', 'name' => 'Old Enchanted Balete Tree', 'category' => 'Nature', 'rating' => 4.2,
                'description' => 'A centuries-old balete tree wrapped in local folklore, with a fish spa in its spring-fed pool.'],

            // ── Local destinations added to cover the full planner city list ──

            // Puerto Princesa
            ['destination' => 'Puerto Princesa', 'name' => 'Puerto Princesa Subterranean River', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'A UNESCO World Heritage underground river winding through a limestone karst cave system to the sea.'],
            ['destination' => 'Puerto Princesa', 'name' => 'Honda Bay', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'A cluster of island-hopping stops with white-sand sandbars, coral reefs, and clear turquoise water.'],
            ['destination' => 'Puerto Princesa', 'name' => 'Iwahig Firefly Watching', 'category' => 'Adventure', 'rating' => 4.3,
                'description' => 'An evening river cruise through mangroves lit by thousands of synchronized fireflies.'],

            // Tagbilaran (Bohol)
            ['destination' => 'Tagbilaran', 'name' => 'Chocolate Hills', 'category' => 'Nature', 'rating' => 4.7,
                'description' => "Over a thousand cone-shaped hills that turn chocolate-brown in the dry season, Bohol's signature landmark."],
            ['destination' => 'Tagbilaran', 'name' => 'Panglao Island Beaches', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'White-sand beaches and reef diving spots just off the coast, among the best in the Visayas.'],
            ['destination' => 'Tagbilaran', 'name' => 'Philippine Tarsier Sanctuary', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'A protected forest reserve where visitors can see the tiny, wide-eyed tarsier in its natural habitat.'],

            // Siargao
            ['destination' => 'Siargao', 'name' => 'Cloud 9', 'category' => 'Adventure', 'rating' => 4.8,
                'description' => "A world-famous surf break and boardwalk, the heart of Siargao's surfing scene."],
            ['destination' => 'Siargao', 'name' => 'Sugba Lagoon', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A mangrove-fringed saltwater lagoon with a floating cottage and cliff-jumping platform.'],
            ['destination' => 'Siargao', 'name' => 'Magpupungko Rock Pools', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'Natural tidal rock pools that appear at low tide, perfect for swimming and cliff jumping.'],

            // Bacolod
            ['destination' => 'Bacolod', 'name' => "The Ruins", 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'The skeletal remains of a grand Italianate mansion, built as a monument to a lost love.'],
            ['destination' => 'Bacolod', 'name' => 'San Sebastian Cathedral', 'category' => 'Culture', 'rating' => 4.4,
                'description' => "Bacolod's century-old cathedral overlooking the city's central plaza."],
            ['destination' => 'Bacolod', 'name' => 'Mambukal Mountain Resort', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A mountain hot-spring resort with waterfalls, hiking trails, and a butterfly sanctuary.'],

            // Zamboanga
            ['destination' => 'Zamboanga', 'name' => 'Pink Beach (Sta. Cruz Island)', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A rare pink-sand beach colored by crushed red organ-pipe coral, reached by boat.'],
            ['destination' => 'Zamboanga', 'name' => 'Fort Pilar', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A 17th-century Spanish fort shrine on the waterfront, a landmark of the city.'],
            ['destination' => 'Zamboanga', 'name' => 'Yakan Weaving Village', 'category' => 'Culture', 'rating' => 4.2,
                'description' => 'A cultural village where Yakan artisans weave traditional handloom textiles.'],

            // General Santos
            ['destination' => 'General Santos', 'name' => 'Gensan Fish Port Complex', 'category' => 'Culture', 'rating' => 4.2,
                'description' => "One of Asia's largest tuna ports, offering an early-morning glimpse into the city's fishing industry."],
            ['destination' => 'General Santos', 'name' => 'Lake Sebu', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A highland lake town near Gensan known for zip-lines over the water and T\'boli weaving traditions.'],
            ['destination' => 'General Santos', 'name' => 'KCC Mall Grounds', 'category' => 'Shopping', 'rating' => 4.1,
                'description' => "One of the city's main shopping and leisure hubs."],

            // Tacloban
            ['destination' => 'Tacloban', 'name' => 'Sto. Niño Shrine and Heritage Museum', 'category' => 'Culture', 'rating' => 4.3,
                'description' => 'A grand former residence turned museum showcasing Filipino art and history.'],
            ['destination' => 'Tacloban', 'name' => 'San Juanico Bridge', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'The longest bridge in the Philippines, linking Leyte and Samar over the San Juanico Strait.'],
            ['destination' => 'Tacloban', 'name' => 'MacArthur Landing Memorial Park', 'category' => 'Culture', 'rating' => 4.4,
                'description' => "A waterfront monument commemorating General MacArthur's WWII return to the Philippines."],

            // El Nido
            ['destination' => 'El Nido', 'name' => 'Big Lagoon', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'A dramatic limestone-walled lagoon with jade-green water, one of El Nido\'s top island-hopping stops.'],
            ['destination' => 'El Nido', 'name' => 'Secret Lagoon', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A hidden lagoon accessible through a narrow rock crevice, tucked inside a limestone cliff.'],
            ['destination' => 'El Nido', 'name' => 'Nacpan Beach', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'A long, wide, undeveloped stretch of white sand backed by coconut palms.'],

            // Coron
            ['destination' => 'Coron', 'name' => 'Kayangan Lake', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'Often called the cleanest lake in the Philippines, framed by dramatic limestone cliffs.'],
            ['destination' => 'Coron', 'name' => 'Barracuda Lake', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'A thermocline-layered lake popular with divers for its unusual temperature gradients.'],
            ['destination' => 'Coron', 'name' => 'Coron Wreck Diving', 'category' => 'Adventure', 'rating' => 4.7,
                'description' => 'World-class wreck diving among sunken WWII Japanese ships resting on the seabed.'],

            // Baguio
            ['destination' => 'Baguio', 'name' => 'Burnham Park', 'category' => 'Nature', 'rating' => 4.3,
                'description' => "Baguio's central park with a boating lake, gardens, and cool mountain air."],
            ['destination' => 'Baguio', 'name' => 'Session Road', 'category' => 'Shopping', 'rating' => 4.2,
                'description' => "The city's main commercial strip, lined with shops, cafes, and night markets."],
            ['destination' => 'Baguio', 'name' => 'Mines View Park', 'category' => 'Nature', 'rating' => 4.1,
                'description' => 'A scenic overlook with views of the old Benguet mining valleys and pine-covered mountains.'],

            // Tagaytay
            ['destination' => 'Tagaytay', 'name' => 'Taal Volcano View', 'category' => 'Nature', 'rating' => 4.7,
                'description' => "Panoramic ridge-top views of Taal Volcano, one of the world's smallest active volcanoes, sitting in its own lake."],
            ['destination' => 'Tagaytay', 'name' => 'Sky Ranch Tagaytay', 'category' => 'Adventure', 'rating' => 4.3,
                'description' => 'A hilltop amusement park with a giant Ferris wheel overlooking Taal Lake.'],
            ['destination' => 'Tagaytay', 'name' => 'People\'s Park in the Sky', 'category' => 'Nature', 'rating' => 4.2,
                'description' => "A hilltop park built on the site of a former presidential retreat, with sweeping views of the ridge."],

            // Vigan
            ['destination' => 'Vigan', 'name' => 'Calle Crisologo', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'A UNESCO World Heritage cobblestone street lined with preserved Spanish-colonial houses.'],
            ['destination' => 'Vigan', 'name' => 'Bantay Bell Tower', 'category' => 'Culture', 'rating' => 4.3,
                'description' => 'A centuries-old watchtower and bell tower offering views over Vigan and the Abra River.'],
            ['destination' => 'Vigan', 'name' => 'Pagburnayan Jar Factory', 'category' => 'Culture', 'rating' => 4.1,
                'description' => 'A traditional pottery workshop where artisans still hand-craft burnay clay jars.'],

            // Batanes
            ['destination' => 'Batanes', 'name' => 'Marlboro Country (Racuh a Payaman)', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'Rolling green pastures on rugged sea cliffs, one of the most photographed views in the Philippines.'],
            ['destination' => 'Batanes', 'name' => 'Vayang Rolling Hills', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'Sweeping grassy hills overlooking the West Philippine Sea, dotted with grazing livestock.'],
            ['destination' => 'Batanes', 'name' => 'Sabtang Island', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A boat-trip destination known for stone Ivatan houses built to withstand fierce typhoons.'],

            // Camiguin
            ['destination' => 'Camiguin', 'name' => 'White Island', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'A pristine, uninhabited sandbar just offshore with views of Camiguin\'s volcanoes.'],
            ['destination' => 'Camiguin', 'name' => 'Sunken Cemetery', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A cemetery submerged by a volcanic eruption in 1871, marked today by a large cross offshore.'],
            ['destination' => 'Camiguin', 'name' => 'Katibawasan Falls', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A tall, cool waterfall set in a lush forest gorge on the slopes of Mount Hibok-Hibok.'],

            // Surigao
            ['destination' => 'Surigao', 'name' => 'Sohoton Cove', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A national park of lagoons, caves, and jellyfish sanctuaries accessible by boat.'],
            ['destination' => 'Surigao', 'name' => 'Bucas Grande Island', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'A remote island cluster known for its stingless-jellyfish lake and hidden lagoons.'],
            ['destination' => 'Surigao', 'name' => 'Mabua Pebble Beach', 'category' => 'Nature', 'rating' => 4.1,
                'description' => 'A unique beach covered in smooth, colorful pebbles instead of sand.'],

            // Laoag
            ['destination' => 'Laoag', 'name' => 'Paoay Sand Dunes', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'Vast desert-like dunes near Laoag popular for 4x4 rides and sandboarding.'],
            ['destination' => 'Laoag', 'name' => 'Paoay Church', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A UNESCO World Heritage baroque church famous for its massive coral-stone buttresses.'],
            ['destination' => 'Laoag', 'name' => 'Sinking Bell Tower', 'category' => 'Culture', 'rating' => 4.2,
                'description' => "A historic Laoag bell tower slowly sinking into the soft coastal ground it was built on."],

            // Legazpi
            ['destination' => 'Legazpi', 'name' => 'Mayon Volcano View', 'category' => 'Nature', 'rating' => 4.8,
                'description' => "Views of Mayon Volcano's near-perfect cone, one of the most iconic volcanoes in the world."],
            ['destination' => 'Legazpi', 'name' => 'Cagsawa Ruins', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'The remains of a church buried by a 1814 Mayon eruption, framed dramatically by the volcano.'],
            ['destination' => 'Legazpi', 'name' => 'Sumlang Lake', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A calm lake with bamboo raft rides and postcard views of Mayon Volcano.'],

            // ── International destinations added to cover the full planner city list ──

            // Singapore
            ['destination' => 'Singapore', 'name' => 'Gardens by the Bay', 'category' => 'Nature', 'rating' => 4.8,
                'description' => "A futuristic nature park famed for its towering Supertrees and climate-controlled domes."],
            ['destination' => 'Singapore', 'name' => 'Marina Bay Sands SkyPark', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "An iconic rooftop observation deck atop Singapore's most recognizable hotel."],
            ['destination' => 'Singapore', 'name' => 'Sentosa Island', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'A resort island with beaches, theme parks, and Universal Studios Singapore.'],

            // Bangkok
            ['destination' => 'Bangkok', 'name' => 'Grand Palace', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'The ornate former royal residence and home to the revered Emerald Buddha temple.'],
            ['destination' => 'Bangkok', 'name' => 'Wat Arun', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'The "Temple of Dawn," a riverside spire-temple encrusted with porcelain and colored glass.'],
            ['destination' => 'Bangkok', 'name' => 'Chatuchak Weekend Market', 'category' => 'Shopping', 'rating' => 4.4,
                'description' => "One of the world's largest weekend markets, with thousands of stalls across a sprawling site."],

            // Bali
            ['destination' => 'Bali', 'name' => 'Tanah Lot Temple', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'An ancient sea temple perched on a rock formation, iconic for its sunset silhouette.'],
            ['destination' => 'Bali', 'name' => 'Tegallalang Rice Terraces', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'Dramatic tiered rice paddies cascading down a valley near Ubud.'],
            ['destination' => 'Bali', 'name' => 'Uluwatu Temple', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'A clifftop temple with dramatic ocean views, famous for its Kecak fire dance at sunset.'],

            // Tokyo
            ['destination' => 'Tokyo', 'name' => 'Senso-ji Temple', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "Tokyo's oldest and most significant Buddhist temple, fronted by the bustling Nakamise shopping street."],
            ['destination' => 'Tokyo', 'name' => 'Shibuya Crossing', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "The world's busiest pedestrian scramble crossing, a defining symbol of modern Tokyo."],
            ['destination' => 'Tokyo', 'name' => 'Tokyo Skytree', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'One of the tallest towers in the world, with observation decks over the entire city.'],

            // Seoul
            ['destination' => 'Seoul', 'name' => 'Gyeongbokgung Palace', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "The largest of Seoul's Five Grand Palaces, with a daily changing-of-the-guard ceremony."],
            ['destination' => 'Seoul', 'name' => 'Bukchon Hanok Village', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A preserved neighborhood of traditional hanok houses on a hillside above the city.'],
            ['destination' => 'Seoul', 'name' => 'Myeongdong Shopping Street', 'category' => 'Shopping', 'rating' => 4.4,
                'description' => "One of Seoul's busiest shopping and street-food districts."],

            // Kuala Lumpur
            ['destination' => 'Kuala Lumpur', 'name' => 'Petronas Twin Towers', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'The iconic twin skyscrapers with a sky bridge and observation deck over the city.'],
            ['destination' => 'Kuala Lumpur', 'name' => 'Batu Caves', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A limestone hill of Hindu temple caves reached via a colorful 272-step staircase.'],
            ['destination' => 'Kuala Lumpur', 'name' => 'Bukit Bintang', 'category' => 'Shopping', 'rating' => 4.3,
                'description' => "KL's premier shopping, dining, and nightlife district."],

            // Hong Kong
            ['destination' => 'Hong Kong', 'name' => 'Victoria Peak', 'category' => 'Nature', 'rating' => 4.7,
                'description' => "Hong Kong's highest point, with sweeping skyline views reached via the historic Peak Tram."],
            ['destination' => 'Hong Kong', 'name' => 'Star Ferry', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'An iconic harbor ferry crossing between Hong Kong Island and Kowloon since 1888.'],
            ['destination' => 'Hong Kong', 'name' => 'Temple Street Night Market', 'category' => 'Shopping', 'rating' => 4.3,
                'description' => 'A bustling nighttime street market known for street food, fortune tellers, and bargain shopping.'],

            // Dubai
            ['destination' => 'Dubai', 'name' => 'Burj Khalifa', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "The world's tallest building, with observation decks offering desert-to-skyline views."],
            ['destination' => 'Dubai', 'name' => 'Dubai Mall & Fountain', 'category' => 'Shopping', 'rating' => 4.6,
                'description' => 'One of the largest malls in the world, fronted by the choreographed Dubai Fountain.'],
            ['destination' => 'Dubai', 'name' => 'Desert Safari', 'category' => 'Adventure', 'rating' => 4.6,
                'description' => 'Dune-bashing, camel rides, and a Bedouin-style dinner camp in the surrounding desert.'],

            // London
            ['destination' => 'London', 'name' => 'Tower of London', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A historic castle and former royal prison, home to the Crown Jewels.'],
            ['destination' => 'London', 'name' => 'British Museum', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "One of the world's great museums, housing artifacts spanning human history from every continent."],
            ['destination' => 'London', 'name' => 'Buckingham Palace', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'The official residence of the British monarch, famed for the Changing of the Guard.'],

            // Paris
            ['destination' => 'Paris', 'name' => 'Eiffel Tower', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "Paris's iconic iron lattice tower and one of the most recognized landmarks on Earth."],
            ['destination' => 'Paris', 'name' => 'Louvre Museum', 'category' => 'Culture', 'rating' => 4.8,
                'description' => "The world's largest art museum, home to the Mona Lisa and the Venus de Milo."],
            ['destination' => 'Paris', 'name' => 'Notre-Dame Cathedral', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A masterpiece of French Gothic architecture on the Île de la Cité.'],

            // New York
            ['destination' => 'New York', 'name' => 'Statue of Liberty', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'The iconic copper statue and symbol of freedom standing in New York Harbor.'],
            ['destination' => 'New York', 'name' => 'Central Park', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'An 843-acre green oasis in the heart of Manhattan.'],
            ['destination' => 'New York', 'name' => 'Times Square', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'The dazzling, billboard-lit commercial hub known as "The Crossroads of the World."'],

            // Sydney
            ['destination' => 'Sydney', 'name' => 'Sydney Opera House', 'category' => 'Culture', 'rating' => 4.8,
                'description' => "Australia's most iconic building, an architectural marvel on Sydney Harbour."],
            ['destination' => 'Sydney', 'name' => 'Bondi Beach', 'category' => 'Nature', 'rating' => 4.6,
                'description' => "One of Australia's most famous beaches, backed by a scenic coastal walking trail."],
            ['destination' => 'Sydney', 'name' => 'Sydney Harbour Bridge', 'category' => 'Adventure', 'rating' => 4.6,
                'description' => 'A massive steel arch bridge offering guided climbs with panoramic harbor views.'],

            // Osaka
            ['destination' => 'Osaka', 'name' => 'Osaka Castle', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A reconstructed feudal-era castle set in a park famous for its cherry blossoms.'],
            ['destination' => 'Osaka', 'name' => 'Dotonbori', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "Osaka's neon-lit canal district, famed for street food and giant illuminated signs."],
            ['destination' => 'Osaka', 'name' => 'Universal Studios Japan', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'A major theme park featuring a dedicated Super Nintendo World zone.'],

            // Taipei
            ['destination' => 'Taipei', 'name' => 'Taipei 101', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "A former world's-tallest skyscraper with an observation deck and giant tuned-mass damper."],
            ['destination' => 'Taipei', 'name' => 'National Palace Museum', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'One of the largest collections of Chinese imperial artifacts and artwork in the world.'],
            ['destination' => 'Taipei', 'name' => 'Shilin Night Market', 'category' => 'Shopping', 'rating' => 4.4,
                'description' => "Taipei's largest and most famous night market, packed with street food stalls."],

            // Rome
            ['destination' => 'Rome', 'name' => 'Colosseum', 'category' => 'Culture', 'rating' => 4.8,
                'description' => 'The largest ancient amphitheater ever built, a defining symbol of the Roman Empire.'],
            ['destination' => 'Rome', 'name' => 'Vatican Museums & Sistine Chapel', 'category' => 'Culture', 'rating' => 4.8,
                'description' => "Home to Michelangelo's Sistine Chapel ceiling and centuries of papal art collections."],
            ['destination' => 'Rome', 'name' => 'Trevi Fountain', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "Rome's most famous baroque fountain, where visitors toss coins to ensure a return trip."],

            // Barcelona
            ['destination' => 'Barcelona', 'name' => 'Sagrada Família', 'category' => 'Culture', 'rating' => 4.8,
                'description' => "Gaudí's still-unfinished basilica, a UNESCO World Heritage masterpiece of unique design."],
            ['destination' => 'Barcelona', 'name' => 'Park Güell', 'category' => 'Nature', 'rating' => 4.6,
                'description' => "A whimsical, mosaic-covered public park designed by Antoni Gaudí."],
            ['destination' => 'Barcelona', 'name' => 'La Rambla', 'category' => 'Shopping', 'rating' => 4.3,
                'description' => "Barcelona's famous tree-lined pedestrian street, packed with shops and street performers."],

            // Amsterdam
            ['destination' => 'Amsterdam', 'name' => 'Anne Frank House', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "The canal-house hideaway where Anne Frank wrote her diary during WWII, now a moving museum."],
            ['destination' => 'Amsterdam', 'name' => 'Van Gogh Museum', 'category' => 'Culture', 'rating' => 4.7,
                'description' => "The world's largest collection of Vincent van Gogh's paintings and letters."],
            ['destination' => 'Amsterdam', 'name' => 'Canal Cruise', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "A boat tour through Amsterdam's UNESCO-listed 17th-century canal ring."],

            // Maldives
            ['destination' => 'Maldives', 'name' => 'Overwater Bungalow Resorts', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'Iconic stilted villas set over turquoise lagoons, the signature Maldivian escape.'],
            ['destination' => 'Maldives', 'name' => 'Coral Reef Snorkeling', 'category' => 'Adventure', 'rating' => 4.7,
                'description' => 'World-class house-reef snorkeling with manta rays, turtles, and vivid coral gardens.'],
            ['destination' => 'Maldives', 'name' => 'Bioluminescent Beach (Vaadhoo)', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A beach where glowing plankton light up the shoreline waves after dark.'],

            // Phuket
            ['destination' => 'Phuket', 'name' => 'Patong Beach', 'category' => 'Nature', 'rating' => 4.4,
                'description' => "Phuket's most famous beach, lined with resorts, bars, and water sports."],
            ['destination' => 'Phuket', 'name' => 'Big Buddha Phuket', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A 45-meter marble Buddha statue on a hilltop with panoramic island views.'],
            ['destination' => 'Phuket', 'name' => 'Phi Phi Islands Tour', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'A boat trip to the dramatic limestone cliffs and lagoons of the Phi Phi archipelago.'],

            // Ho Chi Minh City
            ['destination' => 'Ho Chi Minh City', 'name' => 'Cu Chi Tunnels', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'An extensive underground tunnel network used by Viet Cong guerrillas during the Vietnam War.'],
            ['destination' => 'Ho Chi Minh City', 'name' => 'War Remnants Museum', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A sobering museum documenting the Vietnam War through photography and artifacts.'],
            ['destination' => 'Ho Chi Minh City', 'name' => 'Ben Thanh Market', 'category' => 'Shopping', 'rating' => 4.3,
                'description' => "One of the city's oldest and busiest markets, for local goods, food, and souvenirs."],

            // Hanoi
            ['destination' => 'Hanoi', 'name' => 'Hoan Kiem Lake', 'category' => 'Nature', 'rating' => 4.6,
                'description' => "A tranquil lake in the historic center, home to the red Huc Bridge and Ngoc Son Temple."],
            ['destination' => 'Hanoi', 'name' => 'Old Quarter', 'category' => 'Culture', 'rating' => 4.5,
                'description' => "Hanoi's ancient commercial district of narrow streets, each historically named for a trade."],
            ['destination' => 'Hanoi', 'name' => 'Ha Long Bay Day Trip', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'A UNESCO World Heritage seascape of thousands of limestone karsts, reachable on a day tour from Hanoi.'],

            // Doha
            ['destination' => 'Doha', 'name' => 'Museum of Islamic Art', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'An I.M. Pei-designed museum housing centuries of Islamic art on its own waterfront island.'],
            ['destination' => 'Doha', 'name' => 'Souq Waqif', 'category' => 'Shopping', 'rating' => 4.6,
                'description' => "A traditional market rebuilt in old Qatari style, filled with spices, textiles, and cafes."],
            ['destination' => 'Doha', 'name' => 'The Pearl-Qatar', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'An artificial island of marinas, promenades, and upscale waterfront dining.'],

            // Istanbul
            ['destination' => 'Istanbul', 'name' => 'Hagia Sophia', 'category' => 'Culture', 'rating' => 4.8,
                'description' => 'A former Byzantine cathedral and Ottoman mosque, one of the great architectural wonders of the world.'],
            ['destination' => 'Istanbul', 'name' => 'Blue Mosque', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'A grand 17th-century mosque famed for its six minarets and blue Iznik tilework.'],
            ['destination' => 'Istanbul', 'name' => 'Grand Bazaar', 'category' => 'Shopping', 'rating' => 4.5,
                'description' => 'One of the oldest and largest covered markets in the world, with thousands of shops.'],

            // Toronto
            ['destination' => 'Toronto', 'name' => 'CN Tower', 'category' => 'Culture', 'rating' => 4.6,
                'description' => "Toronto's iconic tower, with a glass floor and observation deck over the skyline and lake."],
            ['destination' => 'Toronto', 'name' => 'Niagara Falls Day Trip', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'One of the most powerful waterfalls in North America, a short trip from downtown Toronto.'],
            ['destination' => 'Toronto', 'name' => 'Distillery District', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A pedestrian-only historic district of Victorian-era industrial buildings turned shops and galleries.'],

            // Los Angeles
            ['destination' => 'Los Angeles', 'name' => 'Hollywood Walk of Fame', 'category' => 'Culture', 'rating' => 4.4,
                'description' => "A sidewalk of over 2,700 stars honoring entertainment industry icons."],
            ['destination' => 'Los Angeles', 'name' => 'Universal Studios Hollywood', 'category' => 'Adventure', 'rating' => 4.6,
                'description' => 'A working film studio and theme park with rides based on major movie franchises.'],
            ['destination' => 'Los Angeles', 'name' => 'Santa Monica Pier', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'An oceanfront amusement pier with a Ferris wheel, arcade, and beach boardwalk.'],
        ];

        foreach ($attractions as $a) {
            // Every destination in this seeder is a Philippine city.
            Attraction::firstOrCreate(
                ['name' => $a['name'], 'destination' => $a['destination']],
                [...$a, 'region' => 'local']
            );
        }
    }
}

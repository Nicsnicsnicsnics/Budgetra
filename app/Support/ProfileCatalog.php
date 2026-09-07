<?php

namespace App\Support;

/**
 * The fixed option sets a travel profile is built from.
 *
 * Lifted out of ProfileBuilder so the AI planner can resolve a traveller's
 * free-text answer ("beach stuff") against the exact same canonical labels the
 * form offers as buttons. Two copies of these lists would silently drift the
 * moment one gained an option the other did not.
 *
 * ProfileBuilder keeps its own consts as aliases of these, so the profile-edit
 * blade that reads ProfileBuilder::ICONS still works untouched.
 */
class ProfileCatalog
{
    public const INTERESTS = [
        'Beach'           => ['Surfing', 'Snorkeling', 'Island Hopping', 'Swimming', 'Diving', 'Beach Camping', 'Kayaking', 'Sunset Watching'],
        'Nature'          => ['Mountains', 'Waterfalls', 'Wildlife', 'Forests', 'Camping', 'Bird Watching', 'National Parks', 'Caves'],
        'Food Trip'       => ['Street Food', 'Fine Dining', 'Local Delicacies', 'Cafes', 'Food Tours', 'Cooking Classes', 'Farmers Markets', 'Food Festivals'],
        'Adventure'       => ['Hiking', 'Diving', 'Ziplining', 'Canyoneering', 'Rock Climbing', 'Whitewater Rafting', 'Paragliding', 'ATV Rides'],
        'Historical Sites'=> ['Churches', 'Ruins', 'Forts', 'Heritage Towns', 'Monuments', 'Ancestral Houses', 'War Memorials', 'Archaeological Sites'],
        'Shopping'        => ['Malls', 'Night Markets', 'Pasalubong', 'Thrift Shops', 'Local Crafts', 'Souvenir Shops', 'Boutiques', 'Flea Markets'],
        'Museums'         => ['Art', 'History', 'Science', 'Culture', 'Natural History', 'Interactive Exhibits', 'Local Heritage', 'Photography'],
        'Nightlife'       => ['Bars', 'Clubs', 'Live Music', 'Night Markets', 'Rooftop Bars', 'Karaoke', 'Night Tours', 'Cultural Shows'],
        'Relaxation'      => ['Spa', 'Beach Resort', 'Hot Springs', 'Wellness', 'Yoga Retreats', 'Massage', 'Meditation', 'Quiet Cafes'],
    ];

    public const ICONS = [
        'Beach'           => 'fa-umbrella-beach',
        'Nature'          => 'fa-leaf',
        'Food Trip'       => 'fa-utensils',
        'Adventure'       => 'fa-person-hiking',
        'Historical Sites'=> 'fa-landmark',
        'Shopping'        => 'fa-bag-shopping',
        'Museums'         => 'fa-building-columns',
        'Nightlife'       => 'fa-moon',
        'Relaxation'      => 'fa-spa',
    ];

    public const IMAGES = [
        'Beach'           => 'beach.jpg',
        'Nature'          => 'nature.jpg',
        'Food Trip'       => 'foodtrip.jpg',
        'Adventure'       => 'adventure.jpg',
        'Historical Sites'=> 'historical.jpg',
        'Shopping'        => 'shopping.jpg',
        'Museums'         => 'museums.jpg',
        'Nightlife'       => 'nightlife.jpg',
        'Relaxation'      => 'relaxation.jpg',
    ];

    public const TRAVEL_STYLES = [
        'Solo'  => ['icon' => 'fa-user', 'desc' => 'Travel at your own pace with a budget built for one.', 'image' => 'solo.jpg'],
        'Group' => ['icon' => 'fa-user-group',      'desc' => 'Divide expenses and explore together, no one overpays.', 'image' => 'group.jpg'],
    ];

    public const TRANSPORTATION_OPTIONS = [
        'Flight' => 'fa-plane',
    ];

    public const TRANSPORTATION_IMAGES = [
        'Flight' => 'flights.jpg',
    ];

    public const ACCOMMODATION_OPTIONS = [
        'Hotel'     => 'fa-hotel',
        'Apartment' => 'fa-building',
        'Inn'       => 'fa-house-chimney',
        'Resort'    => 'fa-umbrella-beach',
    ];

    public const ACCOMMODATION_IMAGES = [
        'Hotel'     => 'hotel.jpg',
        'Apartment' => 'apartment.png',
        'Inn'       => 'inn.jpg',
        'Resort'    => 'resort.jpg',
    ];
}

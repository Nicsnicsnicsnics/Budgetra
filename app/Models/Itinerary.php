<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    protected $table = 'itinerary';

    protected $fillable = [
        'trip_id', 'title', 'type',
        'start_datetime', 'end_datetime', 'location', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime'   => 'datetime',
        ];
    }

    public function trip() { return $this->belongsTo(Trip::class); }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Moment extends Model
{
    protected $fillable = [
        'trip_id', 'place_name', 'description', 'visited_date', 'lat', 'lng',
    ];

    protected function casts(): array
    {
        return [
            'visited_date' => 'date',
            'lat'          => 'decimal:7',
            'lng'          => 'decimal:7',
        ];
    }

    public function trip()   { return $this->belongsTo(Trip::class); }
    public function photos() { return $this->hasMany(MomentPhoto::class); }
}

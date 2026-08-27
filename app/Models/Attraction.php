<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attraction extends Model
{
    protected $fillable = [
        'name', 'destination', 'category', 'region', 'image', 'rating', 'description', 'estimated_cost',
    ];

    protected function casts(): array
    {
        return ['rating' => 'decimal:1', 'estimated_cost' => 'decimal:2'];
    }

    public function reviews() { return $this->hasMany(Review::class); }
}

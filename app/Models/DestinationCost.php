<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationCost extends Model
{
    protected $fillable = [
        'destination', 'category', 'cost_level',
        'multiplier', 'image_url', 'description',
    ];

    protected function casts(): array
    {
        return ['multiplier' => 'decimal:3'];
    }
}

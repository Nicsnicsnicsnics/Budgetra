<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripBudget extends Model
{
    protected $fillable = ['trip_id', 'category', 'estimated_cost', 'actual_spent'];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'actual_spent'   => 'decimal:2',
        ];
    }

    public function trip() { return $this->belongsTo(Trip::class); }
}

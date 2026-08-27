<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    protected $fillable = [
        'user_id', 'trip_id', 'goal_name',
        'target_amount', 'current_savings', 'deadline',
    ];

    protected function casts(): array
    {
        return [
            'target_amount'   => 'decimal:2',
            'current_savings' => 'decimal:2',
            'deadline'        => 'date',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
}

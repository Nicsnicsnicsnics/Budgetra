<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['user_id', 'trip_id', 'type', 'message', 'is_read'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
}

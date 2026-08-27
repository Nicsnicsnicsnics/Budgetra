<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrLog extends Model
{
    protected $fillable = [
        'user_id', 'filename', 'status', 'confidence', 'error_message',
    ];

    protected function casts(): array
    {
        return ['confidence' => 'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
}

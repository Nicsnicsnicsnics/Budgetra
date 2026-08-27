<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversationHistory extends Model
{
    protected $fillable = [
        'user_id', 'messages',
        'ai_from', 'ai_to', 'ai_budget_min', 'ai_budget_max',
        'ai_date_from', 'ai_date_to', 'ai_days', 'ai_travelers', 'ai_currency', 'ai_package',
    ];

    protected function casts(): array
    {
        return [
            'messages'   => 'array',
            'ai_package' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

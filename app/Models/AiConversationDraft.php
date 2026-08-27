<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversationDraft extends Model
{
    protected $fillable = [
        'user_id', 'messages',
        'ai_from', 'ai_to', 'ai_budget_min', 'ai_budget_max',
        'ai_date_from', 'ai_date_to', 'ai_days', 'ai_travelers', 'ai_currency',
        'awaiting_slot', 'miss_count', 'ai_step', 'ai_package', 'ai_gen_count',
        'pending_profile_offer',
    ];

    protected function casts(): array
    {
        return [
            'messages'               => 'array',
            'ai_package'             => 'array',
            'pending_profile_offer'  => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

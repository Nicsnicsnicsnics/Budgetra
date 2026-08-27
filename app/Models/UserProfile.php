<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = ['user_id', 'home_city', 'daily_budget', 'daily_budget_currency', 'daily_budget_local', 'travel_style', 'group_member_emails', 'interests', 'sub_interests', 'preferred_transportation', 'preferred_accommodation'];

    protected $casts = [
        'interests'             => 'array',
        'sub_interests'         => 'array',
        'group_member_emails'   => 'array',
        'daily_budget'          => 'float',
        'daily_budget_local'    => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MomentPhoto extends Model
{
    protected $fillable = ['moment_id', 'photo_path'];

    public function moment() { return $this->belongsTo(Moment::class); }
}

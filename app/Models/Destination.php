<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = ['name', 'country', 'region', 'description', 'image'];

    public function attractions()
    {
        return $this->hasMany(Attraction::class, 'destination', 'name');
    }
}

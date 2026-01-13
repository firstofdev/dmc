<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'manager_name', 'manager_phone', 'address', 'email', 'notes'];

    public function units() {
        return $this->hasMany(Unit::class);
    }
}

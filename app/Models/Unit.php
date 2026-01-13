<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;
    protected $fillable = ['property_id', 'unit_name', 'unit_number', 'yearly_price', 'floor_number', 'status', 'type'];

    public function property() {
        return $this->belongsTo(Property::class);
    }
}

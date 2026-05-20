<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packet extends Model
{
    protected $table = 'packet';
    protected $primaryKey = 'packet_id';
    public $timestamps = false;
    
    // Laravel eloquent treats JSONB as a string. This cast becomes a JSON instead of a string in the frontend
    protected $casts = [
        'l7_attributes' => 'array',
        'l3_attributes' => 'array',
        'l4_attributes' => 'array',
    ];
}

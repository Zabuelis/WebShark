<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpMarker extends Model
{
    protected $table = 'ip_marker';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'ip_address',
        'analyze_counter',
    ];
}

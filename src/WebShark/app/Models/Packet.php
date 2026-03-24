<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packet extends Model
{
    protected $table = 'packet';
    protected $primaryKey = 'packet_id';
    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedisJob extends Model
{
    protected $table = 'redis_job';
    protected $primaryKey = 'redis_id';
    public $timestamps = false;
}

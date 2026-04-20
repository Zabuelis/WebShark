<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisJob extends Model
{
    protected $table = 'analysis_job';
    protected $primaryKey = 'analysis_id';
    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StuffStock extends Model
{
    use SoftDeletes;
    protected $fillable = ['stuff_id', 'total_avaliable', 'total_defec'];
    public $table = 'Stuff__Stocks';
    public function stuff()
    {
        return $this->belongsTo(Stuff::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
     protected $fillable = [
        'version_id','module','action','status','description','meta','created_by','updated_by'
    ];
    public function versionable()
    {
        return $this->morphTo();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

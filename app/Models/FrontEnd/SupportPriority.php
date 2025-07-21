<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;


class SupportPriority extends Model
{
    protected $fillable = ['name', 'level'];

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'priority_id');
    }
}

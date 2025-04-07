<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

class Blog extends Model
{
    protected $table = 'posts';

    protected $guarded = [];
}

<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /**
 * @OA\Schema(
 *     schema="Country",
 *     type="object",
 *     title="Country",
 *     required={"id", "name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="United States")
 * )
 */

    use HasFactory;

    protected $table = 'country';

    protected $fillable = [
        'iso',
        'name',
        'nicename',
        'iso3',
        'numcode',
        'phonecode',
    ];
}

<?php
namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
/**
 * @OA\Schema(
 *     schema="SupportCategory",
 *     title="Support Category",
 *     description="Support ticket category",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Technical Support"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SupportCategory extends Model
{
    protected $fillable = ['name'];

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'category_id');
    }
}

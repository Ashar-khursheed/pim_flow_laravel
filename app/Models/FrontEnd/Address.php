<?php
namespace App\Models\FrontEnd;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FrontEnd\Customer;

class Address extends Model
{
    /**
     * @OA\Schema(
     *     schema="Address",
     *     type="object",
     *     @OA\Property(property="id", type="integer"),
     *     @OA\Property(property="customer_id", type="integer"),
     *     @OA\Property(property="name", type="string"),
     *     @OA\Property(property="phone", type="string"),
     *     @OA\Property(property="email", type="string"),
     *     @OA\Property(property="country", type="string"),
     *     @OA\Property(property="state", type="string"),
     *     @OA\Property(property="city", type="string"),
     *     @OA\Property(property="address", type="string"),
     *     @OA\Property(property="zip_code", type="string"),
     *     @OA\Property(property="is_default", type="boolean")
     * )
 */
    protected $table = 'ec_customer_addresses';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'country',
        'state',
        'city',
        'address',
        'zip_code',
        'customer_id',
        'is_default',
    ];
}

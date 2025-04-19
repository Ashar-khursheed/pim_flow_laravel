<?php
// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ec_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'user_id',
        'shipping_option',
        'shipping_method',
        'status',
        'amount',
        'tax_amount',
        'shipping_amount',
        'description',
        'coupon_code',
        'discount_amount',
        'sub_total',
        'is_confirmed',
        'discount_description',
        'is_finished',
        'cancellation_reason',
        'cancellation_reason_description',
        'completed_at',
        'token',
        'payment_id',
        'proof_file',
        'store_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'is_confirmed' => 'boolean',
        'is_finished' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the address record associated with the order.
     */
    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id');
    }

    /**
     * Get the histories for the order.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id');
    }

    /**
     * Get the returns for the order.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'order_id');
    }

    /**
     * Get the referral record associated with the order.
     */
    public function referral(): HasOne
    {
        return $this->hasOne(OrderReferral::class, 'order_id');
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FrontEnd\Coupon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
class DeactivateExpiredCoupons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentDate = now()->toDateString();
        $count = Coupon::where('expire_date', '<', $currentDate)
            ->where('is_active', '1')
            ->update(['is_active' => '0']);

        $this->info("Deactivated {$count} expired coupons at {$currentDate->toDateTimeString()}");
        Log::error("Deactivated {$count} expired coupons at {$currentDate->toDateTimeString()}");
    }
}

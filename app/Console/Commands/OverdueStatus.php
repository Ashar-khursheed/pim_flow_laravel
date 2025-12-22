<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FrontEnd\Finance;
use App\Models\FrontEnd\FinancesPayment;
class OverdueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:overdue-status';

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
        $this->info('Search finance Sync Started...');
        $today = now()->toDateString();
        Finance::where('status', 'Pending')
        ->whereDate('next_due_date', '<', $today)
        ->update(['status' => 'Overdue']);
        FinancesPayment::where('status', 'Pending')
        ->whereDate('due_date', '<', $today)
        ->update(['status' => 'Overdue']);
        $this->info('Search finance Sync Completed!');
         return Command::SUCCESS;
    }
}

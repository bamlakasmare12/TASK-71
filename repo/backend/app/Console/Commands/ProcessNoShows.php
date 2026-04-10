<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ProcessNoShows extends Command
{
    protected $signature = 'reservations:process-no-shows';
    protected $description = 'Mark unattended reservations as no-shows and evaluate booking freezes';

    public function handle(ReservationService $service): int
    {
        $this->info('Processing no-shows...');

        $count = $service->processNoShows();

        $this->info("Processed {$count} no-show(s).");

        return self::SUCCESS;
    }
}

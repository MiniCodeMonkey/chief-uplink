<?php

namespace App\Console\Commands;

use App\Services\PrdSessionManager;
use Illuminate\Console\Command;

class CheckPrdSessionTimeouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prd:check-timeouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check PRD sessions for timeouts and send warnings';

    /**
     * Execute the console command.
     */
    public function handle(PrdSessionManager $manager): int
    {
        $result = $manager->checkTimeouts();

        if ($result['warnings'] > 0 || $result['expired'] > 0) {
            $this->info("PRD session check: {$result['warnings']} warning(s), {$result['expired']} expired.");
        }

        return Command::SUCCESS;
    }
}

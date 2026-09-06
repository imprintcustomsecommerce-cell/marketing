<?php

namespace App\Console\Commands;

use App\Models\Coverage;
use App\Models\Endorser;
use App\Models\Event;
use App\Models\Obligation;
use App\Models\PrKit;
use App\Models\PublicSubmission;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Empties every working table while leaving the staff accounts in place, so the
 * sample data used for design review can be cleared before real work starts.
 */
class ClearDemoData extends Command
{
    protected $signature = 'imprint:demo-clear {--force : Skip the confirmation}';

    protected $description = 'Delete all events, endorsers, kits, obligations, coverage, tasks and inquiries (staff accounts are kept)';

    /** Children first: rows that point at other rows have to go before them. */
    private const TABLES = [
        Obligation::class,
        Coverage::class,
        PrKit::class,
        Task::class,
        PublicSubmission::class,
        Event::class,
        Endorser::class,
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes all records except the staff accounts. Continue?')) {
            $this->comment('Nothing was deleted.');

            return self::SUCCESS;
        }

        foreach (self::TABLES as $model) {
            $table = (new $model)->getTable();
            $deleted = DB::table($table)->delete();

            $this->line(sprintf('  %-20s %d removed', $table, $deleted));
        }

        $this->info('Demo data cleared. The staff accounts are untouched.');

        return self::SUCCESS;
    }
}

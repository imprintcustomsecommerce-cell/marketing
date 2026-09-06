<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ZipArchive;

class CreateLocalBackup extends Command
{
    protected $signature = 'imprint:backup {--force : Take one even if today already has a backup}';

    protected $description = 'Back up the database and local uploads without a cloud service';

    public function handle(): int
    {
        abort_unless(class_exists(ZipArchive::class), 500, 'PHP zip extension is required for backups.');
        $destination = rtrim((string) config('imprint.backup_path'), '\\/');
        File::ensureDirectoryExists($destination);

        // Runs hourly rather than at a fixed hour, and stops once the day is
        // covered. A shop computer is switched off overnight, so a backup timed
        // for the small hours simply never happens; asking every hour means the
        // first hour the machine is on takes it, whenever that turns out to be.
        if (! $this->option('force') && $this->alreadyBackedUpToday($destination)) {
            $this->line('Today already has a backup. Use --force to take another.');

            return self::SUCCESS;
        }

        $stamp = now()->format('Y-m-d_His');
        $work = storage_path("app/backup-tmp-$stamp");
        File::ensureDirectoryExists($work);

        try {
            $this->dumpDatabase($work);
            $archive = $this->freeName($destination, $stamp);
            $zip = new ZipArchive;
            throw_unless($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, \RuntimeException::class, 'Cannot create backup archive.');
            $this->addDirectory($zip, $work, 'database');
            $private = \Illuminate\Support\Facades\Storage::disk('local')->path('');
            if (File::isDirectory($private)) {
                $this->addDirectory($zip, $private, 'uploads');
            }
            $zip->close();

            $nas = config('imprint.nas_backup_path');
            if ($nas && File::isDirectory($nas)) {
                File::copy($archive, rtrim($nas, '\\/').DIRECTORY_SEPARATOR.basename($archive));
            }
            $this->info("Backup created: $archive");
            $this->prune($destination);

            return self::SUCCESS;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * The stamp only goes down to the second, so two runs in the same second
     * would land on the same name and the archive would quietly replace the one
     * before it. A backup silently destroying another backup is the last thing
     * this command should be capable of.
     */
    private function freeName(string $destination, string $stamp): string
    {
        $candidate = "$destination/imprint-hub_$stamp.zip";

        for ($suffix = 2; File::exists($candidate); $suffix++) {
            $candidate = "$destination/imprint-hub_{$stamp}_$suffix.zip";
        }

        return $candidate;
    }

    private function alreadyBackedUpToday(string $destination): bool
    {
        $prefix = 'imprint-hub_'.now()->format('Y-m-d');

        return collect(File::files($destination))
            ->contains(fn ($file) => str_starts_with($file->getFilename(), $prefix));
    }

    /**
     * Keep a rolling window rather than every archive ever made. A daily zip
     * kept forever quietly fills the disk, and the machine filling up is itself
     * a way to lose the thing being protected.
     */
    private function prune(string $destination): void
    {
        $keep = max(1, (int) config('imprint.backup_keep', 30));

        $archives = collect(File::files($destination))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'imprint-hub_'))
            // Filenames carry a sortable timestamp, so the name is the order.
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values();

        $archives->slice($keep)->each(function ($file) {
            File::delete($file->getPathname());
            $this->line('  removed old backup: '.$file->getFilename());
        });
    }

    private function dumpDatabase(string $work): void
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");
        if (($config['driver'] ?? null) === 'sqlite') {
            // VACUUM INTO, not a file copy. The application is normally serving
            // while this runs, and copying the file byte by byte can catch it
            // mid-write — producing an archive that only turns out to be
            // corrupt on the day someone needs to restore from it. SQLite
            // writes the snapshot itself, consistently, from 3.27 onwards.
            $snapshot = "$work/database.sqlite";
            DB::connection($connection)->statement('VACUUM INTO ?', [$snapshot]);

            return;
        }
        throw_unless(in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true), \RuntimeException::class, 'Only SQLite/MySQL/MariaDB backups are supported.');

        $command = ['mysqldump', '--single-transaction', '--host='.$config['host'], '--port='.(string) $config['port'], '--user='.$config['username']];
        if ($config['password']) {
            $command[] = '--password='.$config['password'];
        }
        $command[] = $config['database'];
        $result = Process::timeout(300)->run($command);
        throw_unless($result->successful(), \RuntimeException::class, 'Database backup failed: '.$result->errorOutput());
        File::put("$work/database.sql", $result->output());
    }

    private function addDirectory(ZipArchive $zip, string $directory, string $prefix): void
    {
        foreach (File::allFiles($directory) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), "$prefix/$relative");
        }
    }
}

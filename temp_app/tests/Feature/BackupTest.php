<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;
use ZipArchive;

/**
 * A backup nobody has restored is a guess. These run the command for real and
 * open what comes out, because the day the archive turns out to be unreadable
 * is the day the shop has already lost the data.
 */
class BackupTest extends TestCase
{
    /**
     * No database trait here, deliberately. RefreshDatabase wraps each test in
     * a transaction and SQLite refuses to VACUUM inside one; the truncating
     * variant never creates the schema on an in-memory database. So the test
     * stands up a real SQLite *file* and migrates it — which is also what the
     * shop actually runs on, and what the snapshot really has to cope with.
     */
    private string $destination;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $unique = uniqid();
        $this->destination = storage_path('app/backup-test-'.$unique);
        $this->database = storage_path('app/backup-test-'.$unique.'.sqlite');

        File::put($this->database, '');
        config()->set('database.connections.backup_testing', [
            'driver' => 'sqlite',
            'database' => $this->database,
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'backup_testing');
        config()->set('imprint.backup_path', $this->destination);
    }

    /**
     * Migrating a file database costs seconds, and only the round-trip test
     * needs the real schema — the rest are about the archive and the pruning,
     * which an empty database serves perfectly well.
     */
    private function withSchema(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('backup_testing');
        File::deleteDirectory($this->destination);
        File::delete($this->database);

        parent::tearDown();
    }

    /** @return string[] */
    private function archives(): array
    {
        return collect(File::files($this->destination))
            ->map(fn ($file) => $file->getFilename())
            ->sort()
            ->values()
            ->all();
    }

    public function test_the_archive_holds_a_database_that_actually_opens(): void
    {
        $this->withSchema();
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('rehearsal/brief.txt', 'Synthetic production brief for restore verification.');

        Event::create([
            'name' => 'Sunday Ride-Out',
            'category' => 'tambike',
            'event_date' => today()->addWeek(),
            'status' => 'confirmed',
        ]);

        $this->artisan('imprint:backup')->assertSuccessful();

        $archives = $this->archives();
        $this->assertCount(1, $archives);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->destination.'/'.$archives[0]) === true);
        $this->assertNotFalse($zip->locateName('database/database.sqlite'));

        $extracted = $this->destination.'/extracted';
        $zip->extractTo($extracted);
        $zip->close();

        // The real check: SQLite opens it, believes it, and the row is there.
        $restored = new PDO('sqlite:'.$extracted.'/database/database.sqlite');
        $this->assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame('Sunday Ride-Out', $restored->query('SELECT name FROM events')->fetchColumn());
        $this->assertSame('Synthetic production brief for restore verification.', File::get($extracted.'/uploads/rehearsal/brief.txt'));
        $restored = null;
    }

    /**
     * The schedule asks every hour because the machine is not on at any
     * predictable time; the command is what makes that one backup a day.
     */
    public function test_it_takes_one_backup_a_day_however_often_it_is_asked(): void
    {
        $this->artisan('imprint:backup')->assertSuccessful();
        $this->artisan('imprint:backup')->assertSuccessful();
        $this->artisan('imprint:backup')->assertSuccessful();

        $this->assertCount(1, $this->archives());
    }

    public function test_force_takes_another_one_anyway(): void
    {
        $this->artisan('imprint:backup')->assertSuccessful();
        $this->artisan('imprint:backup', ['--force' => true])->assertSuccessful();

        $this->assertCount(2, $this->archives());
    }

    public function test_a_new_day_gets_its_own_backup(): void
    {
        $this->artisan('imprint:backup')->assertSuccessful();

        $this->travel(1)->days();
        $this->artisan('imprint:backup')->assertSuccessful();

        $this->assertCount(2, $this->archives());
    }

    public function test_old_archives_are_pruned_so_the_disk_does_not_fill(): void
    {
        // Kept by age rather than by count: the shop machine is not on every
        // day, so a fixed number of files can span far more than a month.
        config()->set('imprint.backup_keep_days', 30);
        File::ensureDirectoryExists($this->destination);

        foreach ([60, 31, 29, 5] as $daysAgo) {
            $name = 'imprint-hub_'.today()->subDays($daysAgo)->format('Y-m-d').'_010000.zip';
            File::put($this->destination.'/'.$name, 'older archive');
        }

        $this->artisan('imprint:backup')->assertSuccessful();

        $kept = $this->archives();
        $this->assertNotContains('imprint-hub_'.today()->subDays(60)->format('Y-m-d').'_010000.zip', $kept);
        $this->assertNotContains('imprint-hub_'.today()->subDays(31)->format('Y-m-d').'_010000.zip', $kept);
        $this->assertContains('imprint-hub_'.today()->subDays(29)->format('Y-m-d').'_010000.zip', $kept);
        $this->assertContains('imprint-hub_'.today()->subDays(5)->format('Y-m-d').'_010000.zip', $kept);
    }

    public function test_it_never_prunes_below_one_archive(): void
    {
        // A misconfigured zero must not wipe the backups it just took.
        config()->set('imprint.backup_keep_days', 0);

        $this->artisan('imprint:backup')->assertSuccessful();

        $this->assertCount(1, $this->archives());
    }

    public function test_files_that_are_not_ours_are_left_alone(): void
    {
        config()->set('imprint.backup_keep_days', 1);
        File::ensureDirectoryExists($this->destination);
        File::put($this->destination.'/notes-from-the-shop.txt', 'do not delete me');

        $this->artisan('imprint:backup')->assertSuccessful();

        $this->assertTrue(File::exists($this->destination.'/notes-from-the-shop.txt'));
    }
}

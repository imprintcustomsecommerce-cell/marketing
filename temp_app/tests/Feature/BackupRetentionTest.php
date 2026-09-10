<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('app/test-backups');
        File::ensureDirectoryExists($this->path);
        File::cleanDirectory($this->path);
        config(['imprint.backup_path' => $this->path, 'imprint.backup_keep_days' => 30]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    private function archive(int $daysAgo): string
    {
        $name = 'imprint-hub_'.today()->subDays($daysAgo)->format('Y-m-d').'_000001.zip';
        File::put($this->path.DIRECTORY_SEPARATOR.$name, 'zip');

        return $name;
    }

    private function names(): array
    {
        return collect(File::files($this->path))->map->getFilename()->sort()->values()->all();
    }

    public function test_archives_past_the_keep_window_are_deleted(): void
    {
        $stale = $this->archive(45);
        $alsoStale = $this->archive(31);
        $recent = $this->archive(10);
        $edge = $this->archive(29);

        $this->artisan('imprint:backup --force')->assertSuccessful();

        $kept = $this->names();
        $this->assertNotContains($stale, $kept);
        $this->assertNotContains($alsoStale, $kept);
        $this->assertContains($recent, $kept, 'inside the window');
        $this->assertContains($edge, $kept, 'a day short of the window still counts');
    }

    public function test_a_shorter_window_can_be_configured(): void
    {
        config(['imprint.backup_keep_days' => 7]);
        $old = $this->archive(10);
        $fresh = $this->archive(3);

        $this->artisan('imprint:backup --force')->assertSuccessful();

        $kept = $this->names();
        $this->assertNotContains($old, $kept);
        $this->assertContains($fresh, $kept);
    }

    public function test_a_backup_is_always_left_behind(): void
    {
        // Everything on disk predates the window by months. Pruning must not
        // empty the folder — an out-of-date backup beats none at all.
        $this->archive(120);
        $this->archive(90);

        $this->artisan('imprint:backup --force')->assertSuccessful();

        $this->assertNotEmpty($this->names());
    }

    public function test_only_one_backup_is_taken_a_day(): void
    {
        $this->artisan('imprint:backup')->assertSuccessful();
        $after = count($this->names());

        $this->artisan('imprint:backup')->expectsOutputToContain('Today already has a backup')->assertSuccessful();

        $this->assertCount($after, $this->names(), 'a second run on the same day should not add another');
    }
}

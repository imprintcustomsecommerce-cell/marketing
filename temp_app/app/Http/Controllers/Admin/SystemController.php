<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemController extends Controller
{
    public function index()
    {
        return view('admin.system.index', ['backups' => $this->backups(), 'sessions' => DB::table('sessions')->where('user_id', auth()->id())->orderByDesc('last_activity')->get()]);
    }


    public function backup()
    {
        Artisan::call('imprint:backup', ['--force' => true]);

        return back()->with('success', 'A fresh backup was created.');
    }

    public function verify()
    {
        $latest = $this->backups()->first();
        if (! $latest) {
            return back()->with('warning', 'No backup is available to verify.');
        } $tmp = storage_path('app/verify-'.uniqid());
        File::ensureDirectoryExists($tmp);
        try {
            $zip = new \ZipArchive;
            throw_unless($zip->open($latest->getPathname()) === true, \RuntimeException::class, 'Backup ZIP cannot be opened.');
            $zip->extractTo($tmp);
            $zip->close();
            $db = $tmp.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
            throw_unless(File::isFile($db), \RuntimeException::class, 'Database snapshot is missing.');
            $pdo = new \PDO('sqlite:'.$db);
            $ok = $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
            throw_unless($ok, \RuntimeException::class, 'Database integrity check failed.');

            return back()->with('success', 'Backup verified: the ZIP opens and its database passes integrity checks.');
        } catch (\Throwable $e) {
            return back()->with('warning', 'Backup verification failed: '.$e->getMessage());
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = rtrim((string) config('imprint.backup_path'), '\\/').DIRECTORY_SEPARATOR.basename($file);
        abort_unless(File::isFile($path), 404);

        return response()->download($path);
    }

    public function sessions(Request $request)
    {
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', 'Other signed-in sessions were closed.');
    }

    private function backups()
    {
        $path = (string) config('imprint.backup_path');

        return File::isDirectory($path) ? collect(File::files($path))->filter(fn ($f) => str_starts_with($f->getFilename(), 'imprint-hub_'))->sortByDesc(fn ($f) => $f->getMTime())->values() : collect();
    }
}

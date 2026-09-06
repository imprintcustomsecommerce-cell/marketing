<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use ZipArchive;

class RehearseBackup extends Command {
 protected $signature='imprint:rehearse-backup {archive : Absolute path to a trusted Imprint Hub SQLite backup}';
 protected $description='Restore a backup into an isolated folder and check database integrity and uploaded files';
 public function handle(): int {
  $archive=realpath($this->argument('archive'));
  if(!$archive || !is_file($archive)) { $this->error('Backup not found.'); return self::FAILURE; }
  $destination=storage_path('app/restore-rehearsals/'.date('Ymd-His').'-'.bin2hex(random_bytes(4)));
  $zip=new ZipArchive;
  if($zip->open($archive)!==true) { $this->error('Cannot open backup.'); return self::FAILURE; }
  try {
   // Validate every entry before extraction, including Windows path separators.
   for($i=0;$i<$zip->numFiles;$i++) {
    $name=$zip->getNameIndex($i);
    if(str_contains($name,'\\') || str_contains($name,':') || in_array('..',explode('/',$name),true)
     || !(str_starts_with($name,'database/') || str_starts_with($name,'uploads/'))) throw new \RuntimeException('Unexpected archive path.');
    $zip->getExternalAttributesIndex($i,$opsys,$attributes);
    if(($attributes >> 16 & 0170000) === 0120000) throw new \RuntimeException('Archive links are not supported.');
   }
   File::ensureDirectoryExists($destination);
   if(!$zip->extractTo($destination)) throw new \RuntimeException('Extraction failed.');
   $database=$destination.'/database/database.sqlite';
   if(!is_file($database)) throw new \RuntimeException('SQLite snapshot missing.');
   $pdo=new PDO('sqlite:'.$database);
   if($pdo->query('PRAGMA integrity_check')->fetchColumn()!=='ok') throw new \RuntimeException('Database integrity failed.');
   if($pdo->query('PRAGMA foreign_key_check')->fetch()) throw new \RuntimeException('Broken database relationships found.');
   $counts=[];
   foreach(['users','events','public_submissions','tasks','coverages'] as $table) $counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
   $files=0;
   for($i=0;$i<$zip->numFiles;$i++) {
    $name=$zip->getNameIndex($i);
    if(!str_starts_with($name,'uploads/') || str_ends_with($name,'/')) continue;
    $stream=$zip->getStream($name); $hash=hash_init('sha256'); hash_update_stream($hash,$stream); fclose($stream);
    if(hash_final($hash)!==hash_file('sha256',$destination.'/'.$name)) throw new \RuntimeException('Restored upload differs from archive.');
    $files++;
   }
   $pdo=null;
   $this->info('Restored database integrity and relationships: OK');
   foreach($counts as $table=>$count) $this->line($table.': '.$count.' records');
   $this->info('Upload files verified: '.$files);
   $this->line('Isolated restored copy retained at: '.$destination);
   $this->line('The live database and uploads were not replaced. This checks data recovery, not browser login.');
   return self::SUCCESS;
  } catch(\Throwable $error) { $this->error($error->getMessage()); $this->line('Rehearsal folder: '.$destination); return self::FAILURE; }
  finally { $zip->close(); }
 }
}

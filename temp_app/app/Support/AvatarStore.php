<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Profile pictures, written straight into public/avatars.
 *
 * Deliberately not the storage disk: that needs `php artisan storage:link`, and
 * a symlink is one more thing to break when this folder is copied between
 * machines. Uploads are re-encoded to a square JPEG, which also strips whatever
 * EXIF (including GPS) came off someone's phone.
 */
class AvatarStore
{
    private const SIZE = 256;

    private const DIRECTORY = 'avatars';

    public function store(User $user, UploadedFile $file): string
    {
        $directory = public_path(self::DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $source = $this->read($file);
        $square = $this->crop($source);

        $name = self::DIRECTORY.'/'.$user->id.'-'.bin2hex(random_bytes(6)).'.jpg';
        imagejpeg($square, public_path($name), 85);

        imagedestroy($source);
        imagedestroy($square);

        $this->forget($user);

        return $name;
    }

    /**
     * Remove the file backing a user's current picture, if any.
     */
    public function forget(User $user): void
    {
        $existing = $user->getOriginal('avatar_path');

        if ($existing && str_starts_with($existing, self::DIRECTORY.'/') && is_file(public_path($existing))) {
            unlink(public_path($existing));
        }
    }

    private function read(UploadedFile $file): \GdImage
    {
        $path = $file->getRealPath();

        return match ($file->getMimeType()) {
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => imagecreatefromjpeg($path),
        };
    }

    /**
     * Centre-crop to a square, then scale to a consistent size — a portrait
     * upload should not come out stretched in a round frame.
     */
    private function crop(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        // Flatten any transparency onto white rather than leaving it black.
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));

        imagecopyresampled(
            $square, $source,
            0, 0,
            (int) (($width - $side) / 2), (int) (($height - $side) / 2),
            self::SIZE, self::SIZE,
            $side, $side,
        );

        return $square;
    }
}

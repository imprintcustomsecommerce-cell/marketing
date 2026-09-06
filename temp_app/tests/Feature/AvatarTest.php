<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'password' => 'secret1234',
            'role' => 'staff',
            'team' => User::TEAM_MARKETING,
            'is_active' => true,
        ]);
    }

    public function test_a_picture_is_stored_squared_and_shown(): void
    {
        $user = $this->staff();

        // Deliberately not square, to prove it is cropped rather than squashed.
        $upload = UploadedFile::fake()->image('selfie.jpg', 900, 600);

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => $upload,
        ])->assertRedirect('/admin/account');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith('avatars/', $user->avatar_path);

        $path = public_path($user->avatar_path);
        $this->written[] = $path;
        $this->assertFileExists($path);

        [$width, $height, $type] = getimagesize($path);
        $this->assertSame($width, $height, 'The stored picture should be square.');
        $this->assertSame(IMAGETYPE_JPEG, $type, 'The stored picture should be re-encoded as JPEG.');

        $this->actingAs($user)->get('/admin/account')
            ->assertOk()
            ->assertSee($user->avatar_path, false);
    }

    public function test_replacing_a_picture_deletes_the_old_file(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => UploadedFile::fake()->image('one.jpg', 400, 400),
        ]);

        $first = public_path($user->fresh()->avatar_path);
        $this->written[] = $first;

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => UploadedFile::fake()->image('two.jpg', 400, 400),
        ]);

        $second = public_path($user->fresh()->avatar_path);
        $this->written[] = $second;

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($first, 'The replaced picture should not be left behind.');
        $this->assertFileExists($second);
    }

    public function test_a_picture_can_be_removed(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => UploadedFile::fake()->image('one.jpg', 300, 300),
        ]);

        $path = public_path($user->fresh()->avatar_path);

        $this->actingAs($user)->delete('/admin/account/avatar')->assertRedirect('/admin/account');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertFileDoesNotExist($path);
    }

    public function test_a_non_image_is_rejected(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => UploadedFile::fake()->create('payload.php', 12, 'text/x-php'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_saving_details_without_choosing_a_picture_keeps_the_current_one(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Marketing 2',
            'email' => 'marketing2@example.test',
            'avatar' => UploadedFile::fake()->image('one.jpg', 300, 300),
        ]);

        $path = $user->fresh()->avatar_path;
        $this->written[] = public_path($path);

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.test',
        ])->assertRedirect('/admin/account');

        $user->refresh();
        $this->assertSame('Ana Reyes', $user->name);
        $this->assertSame($path, $user->avatar_path);
        $this->assertFileExists(public_path($path));
    }
}

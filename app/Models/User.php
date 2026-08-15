<?php

namespace App\Models;

use App\Contracts\OtpVerifiable;
use App\Domains\Employee\Models\Employee;
use App\Models\Traits\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'avatar_url', 'email', 'phone', 'username', 'is_active', 'password', 'active_role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OtpVerifiable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getOtpIdentifier(): string
    {
        return "user:{$this->id}";
    }

    /**
     * Login OTP is one-shot; the issued session/token is the verified state,
     * so there is no persistent flag to set.
     */
    public function markOtpVerified(string $channel): void
    {
        // no-op for the login flow.
    }

    public function isOtpVerified(string $channel): bool
    {
        return false;
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function getAvatarDisk(): string
    {
        return 'avatars';
    }

    public function getAvatarStoragePath(): string
    {
        $employee = $this->employee;

        if ($employee?->personnel_code) {
            return $employee->personnel_code.'/avatar';
        }

        return 'avatars/'.$this->id;
    }

    public function getAvatarFullPath(): ?string
    {
        if (! $this->avatar_url || str_starts_with($this->avatar_url, 'http')) {
            return null;
        }

        return $this->getAvatarStoragePath().'/'.$this->avatar_url;
    }

    public function getServeAvatarUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        return URL::temporarySignedRoute(
            'auth.avatar.serve',
            now()->addHours(24),
            ['user' => $this->id],
            false,
        );
    }

    public function storeAvatar(string $contents, string $extension): string
    {
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $path = $this->getAvatarStoragePath().'/'.$filename;

        Storage::disk($this->getAvatarDisk())->put($path, $contents);

        if ($this->avatar_url) {
            $this->deleteAvatarFromDisk();
        }

        $this->update(['avatar_url' => $filename]);

        return $path;
    }

    public function deleteAvatarFromDisk(): void
    {
        $fullPath = $this->getAvatarFullPath();

        if ($fullPath && Storage::disk($this->getAvatarDisk())->exists($fullPath)) {
            Storage::disk($this->getAvatarDisk())->delete($fullPath);
        }

        $this->update(['avatar_url' => null]);
    }
}

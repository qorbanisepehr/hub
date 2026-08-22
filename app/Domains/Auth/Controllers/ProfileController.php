<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Auth\Events\PasswordChanged;
use App\Domains\Auth\Events\ProfileUpdated;
use App\Domains\Auth\Requests\ChangePasswordRequest;
use App\Domains\Auth\Requests\SwitchProfileRoleRequest;
use App\Domains\Auth\Requests\UpdateProfileRequest;
use App\Domains\Auth\Requests\UploadAvatarRequest;
use App\Domains\Auth\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController
{
    public function __construct(
        private readonly AuditEventDispatcher $audit,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $old = $user->only(['name', 'email']);
        $user->update($request->validated());
        $new = $user->only(['name', 'email']);
        $user->load(['roles', 'activeRole', 'employee']);

        $this->audit->record(new ProfileUpdated($user, $old, $new));

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function updateAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $file = $request->file('avatar');
        $extension = $file->getClientOriginalExtension();
        $contents = file_get_contents($file->getRealPath());

        $request->user()->storeAvatar($contents, $extension);

        $user = $request->user()->load(['roles', 'activeRole', 'employee']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $request->user()->deleteAvatarFromDisk();

        $user = $request->user()->load(['roles', 'activeRole', 'employee']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function switchActiveRole(SwitchProfileRoleRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->setActiveRole($request->validated('role_id'));
        $user->load(['roles', 'activeRole', 'employee']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => $request->validated('password'),
        ]);

        $this->audit->record(new PasswordChanged($user));

        return response()->json([
            'message' => __('auth.password_changed'),
        ]);
    }

    public function serveAvatar(Request $request, int $user): StreamedResponse
    {
        $targetUser = User::findOrFail($user);
        $fullPath = $targetUser->getAvatarFullPath();

        if (! $fullPath || ! Storage::disk($targetUser->getAvatarDisk())->exists($fullPath)) {
            abort(404);
        }

        $mime = Storage::disk($targetUser->getAvatarDisk())->mimeType($fullPath);

        return Storage::disk($targetUser->getAvatarDisk())->response($fullPath, headers: [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age='.now()->addHours(24)->diffInSeconds(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Rules\ClientImageFile;
use App\Support\SurveyPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password. If you just created this user on local admin, create them again on https://mrhari.co.in (production) — the release app does not use your local database.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account is disabled. Ask an admin to activate it.'],
            ]);
        }

        // Mobile app = Manager / PM / Field Executive / Super Admin (full control).
        // Plain Admin stays web-only.
        if ($user->role === User::ROLE_ADMIN) {
            throw ValidationException::withMessages([
                'email' => ['Admin uses the Web portal only. For the mobile app, create a Field Executive, Manager, or Project Manager account.'],
            ]);
        }

        if (! $user->isMobileUser()) {
            throw ValidationException::withMessages([
                'email' => ['This role cannot use the mobile app. Use Field Executive, Manager, Project Manager, or Super Admin.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'seas-mobile')->plainTextToken;
        $user->update(['last_login_at' => now()]);
        ActivityLog::record('api.login', $user);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);
        $user->save();

        ActivityLog::record('api.profile.updated', $user);

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = $data['password'];
        $user->force_password_change = false;
        $user->save();

        ActivityLog::record('api.password.changed', $user);

        return response()->json([
            'message' => 'Password changed successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'file', 'max:5120', new ClientImageFile],
        ]);

        $old = $user->avatar;
        $path = SurveyPhotoStorage::store($request->file('avatar'), 'avatars', 'public', 85, 800);

        $user->avatar = $path;
        $user->save();

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        ActivityLog::record('api.avatar.updated', $user);

        return response()->json([
            'message' => 'Profile picture updated.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function userPayload(User $user): array
    {
        // Relative /storage path — mobile rebuilds absolute URL with its API host.
        $avatarUrl = $user->avatar ? Storage::disk('public')->url($user->avatar) : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'avatar_url' => $avatarUrl,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'supervisor_id' => $user->supervisor_id,
            'can_consumer_survey_approve' => $user->canApproveConsumerSurveys(),
            'scopes' => $user->scopes()->get(['scope_type', 'scope_id']),
        ];
    }
}

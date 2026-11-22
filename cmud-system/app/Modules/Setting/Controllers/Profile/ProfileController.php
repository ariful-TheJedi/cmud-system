<?php

namespace App\Modules\Setting\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Modules\Setting\Requests\Profile\ProfileDeleteRequest;
use App\Modules\Setting\Requests\Profile\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('security/settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except('photo'));
        $previousPhoto = $user->photo_path;
        if ($request->hasFile('photo')) {
            $user->photo_path = $request->file('photo')->store('profile-photos', 'public');
        }

        if ($user->isDirty('email')) {
            DB::table('password_reset_tokens')
                ->where('email', $user->getOriginal('email'))
                ->delete();

            $user->email_verified_at = null;
        }

        $user->save();

        // Delete the old upload only after the new profile has been saved.
        if ($previousPhoto && $previousPhoto !== $user->photo_path) {
            Storage::disk('public')->delete($previousPhoto);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

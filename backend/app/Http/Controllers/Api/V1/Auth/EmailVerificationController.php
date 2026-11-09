<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    use ApiResponses;

    /**
     * Verify a user's email via the signed link from their inbox, then bounce
     * them back to the frontend login screen.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away($frontend.'/login?verified=0');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away($frontend.'/login?verified=1');
    }

    /**
     * Resend the verification email to the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->message('Your email address is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->message('A new verification link has been sent to your email address.');
    }

    public function status(Request $request): JsonResponse
    {
        return $this->ok(['verified' => $request->user()->hasVerifiedEmail()]);
    }
}

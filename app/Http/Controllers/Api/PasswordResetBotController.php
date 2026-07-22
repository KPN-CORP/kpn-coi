<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PasswordResetBotController extends Controller
{
    /**
     * How long the magic reset link stays valid.
     */
    private const LINK_TTL_MINUTES = 60;

    /**
     * Webhook payload:
     *   { "app": "", "sender": "", "message": "reset password commitment corner",
     *     "group_name": "", "phone": "081122299933" }
     *
     * Always replies with a single { "reply": "..." } string so the bot can
     * forward it verbatim to the user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Optional shared secret: when RESET_BOT_API_KEY is set, the caller must
        // send it as the X-Api-Key header. Left unset, the endpoint is open.
        $expectedKey = config('services.reset_bot.key');

        if (
            ! empty($expectedKey)
            && ! hash_equals((string) $expectedKey, (string) $request->header('X-Api-Key'))
        ) {
            return response()->json(['reply' => 'Unauthorized request.'], 401);
        }

        $candidates = $this->phoneCandidates((string) $request->input('phone', ''));

        if (empty($candidates)) {
            return $this->reply('Please provide a valid phone number.');
        }

        $user = NonEmployee::query()
            ->whereIn('personal_mobile_number', $candidates)
            ->with('user')
            ->first()
            ?->user;

        if (! $user) {
            return $this->reply('This phone number is not registered. Please contact the admin.');
        }

        // Already completed self-service setup once — further resets are manual.
        if ($user->password_set_at !== null) {
            return $this->reply('Please call the admin to reset the password.');
        }

        return $this->reply($this->magicLink($user));
    }

    /**
     * Signed, time-limited link that lets the user set their password once.
     */
    private function magicLink(NonEmployeeUser $user): string
    {
        return URL::temporarySignedRoute(
            'password.magic',
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['user' => $user->getKey()],
        );
    }

    /**
     * Normalise the phone into the formats we might have stored, so a leading
     * 0 and a 62 country code match the same person.
     */
    private function phoneCandidates(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        if (str_starts_with($digits, '62')) {
            $variants[] = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $variants[] = '62' . substr($digits, 1);
        }

        return array_values(array_unique($variants));
    }

    private function reply(string $message): JsonResponse
    {
        return response()->json(['reply' => $message]);
    }
}

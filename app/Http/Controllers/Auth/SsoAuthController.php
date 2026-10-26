<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SsoCallbackRequest;
use App\Services\SsoUserService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! config('sso.enabled') || ! config('sso.client_id') || ! config('sso.client_secret')) {
            return to_route('filament.admin.auth.login')->withErrors(['sso' => 'SSO belum dikonfigurasi. Hubungi superadmin.']);
        }

        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('sso.attempt', ['state' => $state, 'verifier' => $verifier, 'started_at' => now()->timestamp]);

        return redirect()->away(rtrim(config('sso.server_url'), '/').'/oauth/authorize?'.http_build_query([
            'client_id' => config('sso.client_id'), 'redirect_uri' => config('sso.redirect_uri'),
            'response_type' => 'code', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(SsoCallbackRequest $request, SsoUserService $users): RedirectResponse
    {
        $data = $request->validated();
        $attempt = $request->session()->pull('sso.attempt');

        if (! config('sso.enabled')) {
            return $this->failure('disabled');
        }
        if (! is_array($attempt) || ! isset($attempt['state'], $attempt['verifier'], $attempt['started_at'])) {
            return $this->failure('session_missing');
        }
        if (! hash_equals($attempt['state'], $data['state'])) {
            return $this->failure('state_mismatch');
        }
        if ($attempt['started_at'] < now()->timestamp - 300) {
            return $this->failure('session_expired');
        }

        $stage = 'token_exchange';
        try {
            $server = rtrim(config('sso.server_url'), '/');
            $tokenResponse = Http::asForm()->acceptJson()->withoutRedirecting()->timeout(15)->post($server.'/oauth/token', [
                'grant_type' => 'authorization_code', 'client_id' => config('sso.client_id'),
                'client_secret' => config('sso.client_secret'), 'redirect_uri' => config('sso.redirect_uri'),
                'code' => $data['code'], 'code_verifier' => $attempt['verifier'],
            ])->throw();
            $token = $tokenResponse->json('access_token');

            if (! $tokenResponse->successful() || ! is_string($token) || $token === '') {
                return $this->failure('invalid_token_response', ['http_status' => $tokenResponse->status()]);
            }

            $stage = 'profile_fetch';
            $profileResponse = Http::withToken($token)->acceptJson()->withoutRedirecting()->timeout(15)->get($server.'/api/sso/user')->throw();
            if (! $profileResponse->successful()) {
                return $this->failure('profile_fetch', ['http_status' => $profileResponse->status()]);
            }
            $identity = $profileResponse->json();
        } catch (ConnectionException $exception) {
            return $this->failure($stage, ['connection_failed' => true]);
        } catch (RequestException $exception) {
            $error = $exception->response->json('error');
            $context = ['http_status' => $exception->response->status()];
            if (is_string($error) && in_array($error, ['invalid_client', 'invalid_grant', 'invalid_request', 'invalid_scope', 'unsupported_grant_type', 'server_error'], true)) {
                $context['oauth_error'] = $error;
            }

            return $this->failure($stage, $context);
        }

        if (! is_array($identity) || ! isset($identity['id']) || (! is_string($identity['id']) && ! is_int($identity['id']))) {
            return $this->failure('invalid_identity');
        }
        $identity['id'] = (string) $identity['id'];
        $validator = Validator::make($identity, ['id' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            return $this->failure('invalid_identity');
        }

        try {
            $user = $users->resolve($validator->validated());
        } catch (ValidationException $exception) {
            return to_route('filament.admin.auth.login')->withErrors($exception->errors());
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('filament.admin.pages.dashboard'));
    }

    /** @param array<string, int|string|bool> $context */
    private function failure(string $reason, array $context = []): RedirectResponse
    {
        Log::warning('SIVMI SSO callback failed', ['reason' => $reason, ...$context]);
        $code = match ($reason) {
            'disabled' => 'SSO-CONFIG',
            'session_missing', 'state_mismatch' => 'SSO-SESSION',
            'session_expired' => 'SSO-EXPIRED',
            'token_exchange', 'invalid_token_response' => 'SSO-TOKEN',
            default => 'SSO-PROFILE',
        };

        return to_route('filament.admin.auth.login')->withErrors(['sso' => 'Login SSO gagal ('.$code.'). Silakan coba kembali atau hubungi administrator.']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sso.enabled' => true, 'sso.server_url' => 'https://sso.example.test', 'sso.client_id' => 'crm', 'sso.client_secret' => 'test-secret', 'sso.redirect_uri' => 'https://crm.example.test/sso/callback']);
        Http::preventStrayRequests();
    }

    public function test_filament_login_displays_sso_button_only_when_enabled(): void
    {
        $this->get(route('filament.admin.auth.login'))->assertOk()->assertSee('Masuk dengan akun SIVMI')->assertDontSee('test-secret');
        config(['sso.enabled' => false]);
        $this->get(route('filament.admin.auth.login'))->assertOk()->assertDontSee('Masuk dengan akun SIVMI');
    }

    public function test_redirect_uses_pkce_and_state_without_exposing_secret(): void
    {
        $response = $this->get(route('sso.login'))->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('crm', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(session('sso.attempt.state'), $query['state']);
        $this->assertArrayNotHasKey('client_secret', $query);
    }

    public function test_active_existing_account_is_linked_without_changing_role_branch_or_password(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $user = User::factory()->create(['email' => 'person@example.test', 'role' => UserRole::Keuangan, 'cabang_id' => $branch->getKey(), 'aktif' => true]);
        $passwordHash = $user->password;
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.pages.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('123', $user->fresh()->sso_id);
        $this->assertSame(UserRole::Keuangan, $user->fresh()->role);
        $this->assertSame($branch->getKey(), $user->fresh()->cabang_id);
        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sso.example.test/oauth/token' && $request['code_verifier'] === 'verifier' && $request['client_secret'] === 'test-secret');
    }

    public function test_unknown_account_is_not_provisioned_without_crm_approval(): void
    {
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Http::assertSentCount(2);
    }

    public function test_inactive_account_is_not_reactivated_or_linked(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'role' => UserRole::Staff, 'aktif' => false]);
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertFalse($user->fresh()->aktif);
        $this->assertNull($user->fresh()->sso_id);
        Http::assertSentCount(2);
    }

    public function test_different_linked_identity_cannot_take_over_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'role' => UserRole::Staff, 'aktif' => true]);
        $user->sso_id = 'other-id';
        $user->save();
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertSame('other-id', $user->fresh()->sso_id);
        Http::assertSentCount(2);
    }

    public function test_wrong_or_expired_state_is_rejected_without_network_requests(): void
    {
        $this->withSession($this->attempt())->get('/sso/callback?state=wrong&code=code')->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->withSession(['sso.attempt' => ['state' => 'state', 'verifier' => 'verifier', 'started_at' => now()->timestamp - 301]])->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_invalid_client_logs_only_safe_metadata_and_returns_to_filament_login(): void
    {
        Log::shouldReceive('warning')->once()->with('SIVMI SSO callback failed', ['reason' => 'token_exchange', 'http_status' => 401, 'oauth_error' => 'invalid_client']);
        Http::fake(['https://sso.example.test/oauth/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Do not log test-secret'], 401)]);
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_missing_callback_fields_return_to_local_login_even_with_external_referrer(): void
    {
        $this->from('https://sso.example.test/oauth/authorize')->get(route('sso.callback'))
            ->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors(['state', 'code']);
        Http::assertNothingSent();
    }

    public function test_disabled_sso_does_not_contact_provider(): void
    {
        config(['sso.enabled' => false]);
        $this->get(route('sso.login'))->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        Http::assertNothingSent();
    }

    public function test_invalid_remote_profile_does_not_authenticate_or_modify_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'role' => UserRole::Staff, 'aktif' => true]);
        Http::fake([
            'https://sso.example.test/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            'https://sso.example.test/api/sso/user' => Http::response(['id' => '123', 'name' => 'Name', 'email' => 'invalid-email']),
        ]);
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->sso_id);
        Http::assertSentCount(2);
    }

    public function test_unknown_local_role_is_rejected_without_attempting_to_authenticate(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'aktif' => true]);
        DB::table('users')->where('id', $user->id)->update(['role' => 'unsupported-role']);
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.auth.login'))->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertNull($user->fresh()->sso_id);
        Http::assertSentCount(2);
    }

    public function test_linked_identity_keeps_local_permissions_when_sso_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test', 'role' => UserRole::Staff, 'aktif' => true]);
        $user->sso_id = '123';
        $user->save();
        $this->fakeIdentity();
        $this->withSession($this->attempt())->get($this->callbackUrl())->assertRedirect(route('filament.admin.pages.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertSame(UserRole::Staff, $user->fresh()->role);
        Http::assertSentCount(2);
    }

    private function fakeIdentity(): void
    {
        Http::fake([
            'https://sso.example.test/oauth/token' => Http::response(['access_token' => 'test-access-token']),
            'https://sso.example.test/api/sso/user' => Http::response(['id' => '123', 'name' => 'SSO User', 'email' => 'person@example.test', 'role' => 'superadmin', 'cabang_id' => 999, 'aktif' => true]),
        ]);
    }

    private function attempt(): array
    {
        return ['sso.attempt' => ['state' => 'state', 'verifier' => 'verifier', 'started_at' => now()->timestamp]];
    }

    private function callbackUrl(): string
    {
        return '/sso/callback?state=state&code=code';
    }
}

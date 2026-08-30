<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppMatchStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Filament\Resources\WhatsAppAccounts\Pages\CreateWhatsAppAccount;
use App\Models\ActivityLog;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserWhatsAppAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WahaClient;
use App\Services\WhatsApp\WahaSessionService;
use App\Services\WhatsApp\WhatsAppChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class WahaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'waha-test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.waha.webhook_hmac_secret', self::SECRET);
        config()->set('services.waha.webhook_url', 'http://crm.test/api/webhooks/waha');
    }

    public function test_webhook_rejects_invalid_hmac(): void
    {
        $this->postJson('/api/webhooks/waha', ['event' => 'message', 'session' => 'test', 'payload' => []], [
            'X-Webhook-Hmac' => 'invalid',
            'X-Webhook-Hmac-Algorithm' => 'sha512',
        ])->assertUnauthorized();
    }

    public function test_incoming_message_creates_a_lead_and_records_the_chat(): void
    {
        [$branch, $staff, $account] = $this->account();

        $response = $this->sendWebhook($this->messagePayload($account->session_name, '628123456789@c.us', 'msg-new'));
        $response->assertAccepted()->assertJson(['accepted' => true, 'duplicate' => false]);

        $lead = Lead::query()->sole();
        $this->assertSame('628123456789', $lead->no_wa_normalized);
        $this->assertSame('WhatsApp', $lead->kanal_masuk->value);
        $this->assertSame($branch->getKey(), $lead->cabang_id);
        $this->assertSame($staff->getKey(), $lead->diserahkan_ke_user_id);
        $this->assertNotNull($lead->whatsapp_first_seen_at);

        $conversation = WhatsAppConversation::query()->sole();
        $this->assertSame($lead->getKey(), $conversation->lead_id);
        $this->assertSame(WhatsAppMatchStatus::NewLead, $conversation->match_status);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertTrue($staff->notifications()->where('data->title', 'Pesan WhatsApp baru')->exists());
    }

    public function test_existing_number_is_linked_without_creating_a_duplicate_lead(): void
    {
        [$branch, $staff, $account] = $this->account();
        $lead = Lead::create($this->leadData($branch, $staff, 'Existing Lead', '08123456789'));

        $payload = $this->messagePayload($account->session_name, '628123456789@c.us', 'msg-existing');
        $this->sendWebhook($payload)->assertAccepted();
        $this->sendWebhook($payload)->assertAccepted()->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertSame($lead->getKey(), WhatsAppConversation::query()->sole()->lead_id);
        $this->assertSame(WhatsAppMatchStatus::Matched, WhatsAppConversation::query()->sole()->match_status);
    }

    public function test_gows_lid_message_uses_sender_alt_for_lead_matching(): void
    {
        [$branch, $staff, $account] = $this->account();

        $this->sendWebhook($this->gowsMessagePayload($account->session_name))->assertAccepted();

        $lead = Lead::query()->sole();
        $conversation = WhatsAppConversation::query()->sole();

        $this->assertSame('6285320008589', $lead->no_wa_normalized);
        $this->assertSame('Pengirim GOWS', $lead->nama);
        $this->assertSame('109719301685330@lid', $conversation->chat_id);
        $this->assertSame('6285320008589', $conversation->contact_phone_e164);
        $this->assertSame('Pengirim GOWS', $conversation->contact_name);
        $this->assertSame($lead->getKey(), $conversation->lead_id);
        $this->assertSame(WhatsAppMatchStatus::NewLead, $conversation->match_status);
        $this->assertSame($branch->getKey(), $lead->cabang_id);
        $this->assertSame($staff->getKey(), $lead->diserahkan_ke_user_id);
    }

    public function test_duplicate_normalized_lead_phone_is_rejected(): void
    {
        [$branch, $staff] = $this->account();
        Lead::create($this->leadData($branch, $staff, 'Student One', '08123456789'));

        try {
            Lead::create($this->leadData($branch, $staff, 'Student Two', '+62 812-3456-789'));
            $this->fail('Nomor WhatsApp ter-normalisasi yang sama seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Nomor WhatsApp sudah digunakan oleh lead lain. Periksa data lead yang sudah ada.',
                $exception->errors()['no_wa'][0],
            );
        }

        $this->assertDatabaseCount('leads', 1);
    }

    public function test_matching_number_from_another_branch_requires_manual_review(): void
    {
        [, , $account] = $this->account();
        $otherBranch = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'kota' => 'Jakarta Selatan', 'aktif' => true]);
        $otherStaff = User::factory()->create(['cabang_id' => $otherBranch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        Lead::create($this->leadData($otherBranch, $otherStaff, 'Lead JakSel', '08123456789'));

        $this->sendWebhook($this->messagePayload($account->session_name, '628123456789@c.us', 'msg-cross-branch'))->assertAccepted();

        $conversation = WhatsAppConversation::query()->sole();
        $this->assertNull($conversation->lead_id);
        $this->assertSame(WhatsAppMatchStatus::Conflict, $conversation->match_status);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_staff_only_sees_their_own_whatsapp_conversations(): void
    {
        [$branch, $staff, $account] = $this->account();
        $other = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $otherAccount = UserWhatsAppAccount::create(['user_id' => $other->id, 'cabang_id' => $branch->getKey(), 'phone_e164' => '628999999999', 'session_name' => 'user-'.$other->id, 'aktif' => true]);
        WhatsAppConversation::create(['whatsapp_account_id' => $account->id, 'chat_id' => '628111111111@c.us']);
        WhatsAppConversation::create(['whatsapp_account_id' => $otherAccount->id, 'chat_id' => '628222222222@c.us']);

        $this->assertSame(1, WhatsAppConversation::query()->visibleTo($staff)->count());
    }

    public function test_staff_can_send_a_text_message_from_their_working_waha_account(): void
    {
        [, $staff, $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
            'contact_phone_e164' => '628123456789',
            'contact_name' => 'Calon Siswa',
        ]);

        $this->mock(WahaClient::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('sendText')->once()->with(
                $account->session_name,
                '628123456789@c.us',
                'Halo, jadwal konsultasi tersedia hari ini.',
            )->andReturn([
                'id' => 'true_628123456789@c.us_outbound-1',
                'timestamp' => now()->timestamp,
            ]);
        });

        $message = app(WhatsAppChatService::class)->sendText(
            $conversation,
            $staff,
            'Halo, jadwal konsultasi tersedia hari ini.',
        );

        $this->assertSame(WhatsAppMessageDirection::Outbound, $message->direction);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->processing_status);
        $this->assertSame('true_628123456789@c.us_outbound-1', $message->external_message_id);
        $this->assertSame($message->sent_at->timestamp, $conversation->fresh()->last_message_at->timestamp);
        $this->assertTrue(ActivityLog::where('event', 'whatsapp_message_sent')->where('user_id', $staff->getKey())->exists());
    }

    public function test_staff_can_send_an_image_and_the_private_media_is_authorized(): void
    {
        Storage::fake('local');
        [, $staff, $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
            'contact_name' => 'Calon Siswa',
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $file = UploadedFile::fake()->createWithContent('brosur.png', $png);

        $this->mock(WahaClient::class, function (MockInterface $mock) use ($account, $png): void {
            $mock->shouldReceive('sendImage')->once()->withArgs(fn (string $session, string $chatId, string $mime, string $filename, string $data, ?string $caption): bool => $session === $account->session_name
                && $chatId === '628123456789@c.us'
                && $mime === 'image/png'
                && $filename === 'brosur.png'
                && $data === $png
                && $caption === 'Informasi program'
            )->andReturn(['id' => 'outbound-image-1', 'timestamp' => now()->timestamp]);
        });

        $message = app(WhatsAppChatService::class)->sendMedia($conversation, $staff, $file, 'Informasi program');

        $this->assertSame('image', $message->message_type);
        $this->assertTrue($message->has_media);
        Storage::disk('local')->assertExists($message->media_path);

        $this->actingAs($staff)
            ->get(route('waha.messages.media', $message))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $otherStaff = User::factory()->create(['cabang_id' => $account->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $this->actingAs($otherStaff)
            ->get(route('waha.messages.media', $message))
            ->assertNotFound();
    }

    public function test_incoming_image_is_downloaded_from_waha_and_stored_privately(): void
    {
        Storage::fake('local');
        [, , $account] = $this->account();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $payload = $this->messagePayload($account->session_name, '628123456789@c.us', 'incoming-image-1');
        data_set($payload, 'payload.body', 'Foto bukti');
        data_set($payload, 'payload.hasMedia', true);
        data_set($payload, 'payload.media', [
            'url' => 'http://waha:3000/api/files/session/incoming-image-1.jpg',
            'mimetype' => 'image/png',
            'filename' => 'bukti.png',
        ]);

        $this->mock(WahaClient::class, function (MockInterface $mock) use ($png): void {
            $mock->shouldReceive('downloadMedia')->once()
                ->with('http://waha:3000/api/files/session/incoming-image-1.jpg')
                ->andReturn(['body' => $png, 'content_type' => 'image/png']);
        });

        $this->sendWebhook($payload)->assertAccepted();

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame('image', $message->message_type);
        $this->assertSame('bukti.png', data_get($message->raw_payload, 'media.filename'));
        Storage::disk('local')->assertExists($message->media_path);
    }

    public function test_failed_outbound_message_is_recorded_and_notifies_the_sender(): void
    {
        [, $staff, $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
            'contact_name' => 'Calon Siswa',
        ]);

        $this->mock(WahaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendText')->once()->andThrow(new RuntimeException('WAHA timeout'));
        });

        try {
            app(WhatsAppChatService::class)->sendText($conversation, $staff, 'Pesan yang gagal');
            $this->fail('Pengiriman seharusnya gagal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Pesan gagal dikirim', $exception->getMessage());
        }

        $failed = WhatsAppMessage::query()->sole();
        $this->assertSame(WhatsAppMessageStatus::Failed, $failed->processing_status);
        $this->assertTrue($staff->notifications()->where('data->title', 'Pesan WhatsApp gagal dikirim')->exists());
    }

    public function test_staff_cannot_send_from_another_users_whatsapp_account(): void
    {
        [$branch, , $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);
        $otherStaff = User::factory()->create([
            'cabang_id' => $branch->getKey(),
            'role' => UserRole::Staff,
            'aktif' => true,
        ]);
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
        ]);

        $this->expectException(AuthorizationException::class);

        app(WhatsAppChatService::class)->sendText($conversation, $otherStaff, 'Pesan tidak sah');
    }

    public function test_message_ack_updates_outbound_delivery_status(): void
    {
        [, , $account] = $this->account();
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
        ]);
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->getKey(),
            'whatsapp_account_id' => $account->getKey(),
            'external_message_id' => 'true_628123456789@c.us_ack-test',
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => 'text',
            'body' => 'Tes ACK',
            'sent_at' => now(),
            'from_me' => true,
            'processing_status' => WhatsAppMessageStatus::Sent,
        ]);

        $this->sendWebhook([
            'event' => 'message.ack',
            'session' => $account->session_name,
            'engine' => 'GOWS',
            'payload' => [
                'id' => $message->external_message_id,
                'fromMe' => true,
                'ack' => 3,
                'ackName' => 'READ',
            ],
        ])->assertAccepted();

        $this->assertSame(WhatsAppMessageStatus::Read, $message->fresh()->processing_status);
    }

    public function test_chat_page_shows_the_composer_for_a_visible_conversation(): void
    {
        [, $staff, $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);
        $conversation = WhatsAppConversation::create([
            'whatsapp_account_id' => $account->getKey(),
            'chat_id' => '628123456789@c.us',
            'contact_name' => 'Pengguna Uji',
        ]);

        $this->actingAs($staff)
            ->get('/admin/inbox-whatsapp/'.$conversation->getKey())
            ->assertOk()
            ->assertSee('Chat dengan Pengguna Uji')
            ->assertSee('Tulis pesan WhatsApp')
            ->assertSee('WhatsApp terhubung');
    }

    public function test_existing_waha_session_can_enable_chat_webhook_events(): void
    {
        [, , $account] = $this->account();

        $this->mock(WahaClient::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('updateSession')->once()->withArgs(function (string $session, array $config) use ($account): bool {
                return $session === $account->session_name
                    && data_get($config, 'webhooks.0.url') === 'http://crm.test/api/webhooks/waha'
                    && data_get($config, 'webhooks.0.events') === ['message', 'message.any', 'message.ack', 'session.status']
                    && data_get($config, 'webhooks.0.hmac.key') === self::SECRET;
            })->andReturn(['name' => $account->session_name, 'status' => 'WORKING']);
        });

        app(WahaSessionService::class)->syncWebhooks($account);

        $this->assertNotNull($account->fresh()->metadata['webhooks_synced_at'] ?? null);
    }

    public function test_admin_can_create_whatsapp_account_but_staff_cannot(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'kota' => 'Bandung', 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($admin)->get('/admin/koneksi-whatsapp')
            ->assertOk()
            ->assertSee('Koneksi WhatsApp')
            ->assertSee('Ringkasan kesehatan WhatsApp')
            ->assertSee('Daftar akun dan session')
            ->assertSee('Statistik pesan dan percakapan')
            ->assertSee('Masalah yang perlu ditangani')
            ->assertSee('Riwayat sinkronisasi dan audit');
        $this->get('/admin/inbox-whatsapp')->assertOk()->assertSee('Inbox WhatsApp');

        Livewire::actingAs($admin)->test(CreateWhatsAppAccount::class)
            ->set('data', ['user_id' => $staff->getKey(), 'phone_e164' => '0812-9876-5432', 'aktif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $account = UserWhatsAppAccount::query()->sole();
        $this->assertSame('6281298765432', $account->phone_e164);
        $this->assertSame('sivmi-user-'.$staff->getKey(), $account->session_name);

        $this->actingAs($staff)->get('/admin/koneksi-whatsapp/create')->assertForbidden();
    }

    public function test_working_waha_session_can_be_stopped_without_deleting_account_data(): void
    {
        [, , $account] = $this->account();
        $account->update(['status' => WhatsAppAccountStatus::Working]);

        $this->mock(WahaClient::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('stopSession')->once()->with($account->session_name)->andReturn(['status' => 'STOPPED']);
        });

        app(WahaSessionService::class)->stop($account);

        $this->assertSame(WhatsAppAccountStatus::Disconnected, $account->fresh()->status);
        $this->assertNotNull($account->fresh()->last_seen_at);
    }

    public function test_qr_route_restarts_a_failed_session_and_returns_the_new_qr(): void
    {
        [, $admin, $account] = $this->account();
        $admin->update(['role' => UserRole::AdminCabang]);

        $this->mock(WahaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getSession')->once()->andReturn(['status' => 'FAILED']);
            $mock->shouldReceive('restartSession')->once()->andReturn(['status' => 'STARTING']);
            $mock->shouldReceive('getSession')->once()->andReturn(['status' => 'SCAN_QR_CODE']);
            $mock->shouldReceive('qrCode')->once()->andReturn(['body' => 'png-content', 'content_type' => 'image/png']);
        });

        $this->actingAs($admin)
            ->get(route('waha.accounts.qr', $account))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('png-content');
    }

    public function test_qr_route_returns_a_recovery_state_instead_of_an_internal_server_error(): void
    {
        [, $admin, $account] = $this->account();
        $admin->update(['role' => UserRole::AdminCabang]);

        $this->mock(WahaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getSession')->once()->andReturn(['status' => 'FAILED']);
            $mock->shouldReceive('restartSession')->once()->andThrow(new RuntimeException('WAHA unavailable'));
        });

        $this->actingAs($admin)
            ->get(route('waha.accounts.qr', $account))
            ->assertStatus(503)
            ->assertSee('QR WhatsApp belum siap')
            ->assertDontSee('Internal Server Error');
    }

    private function sendWebhook(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/webhooks/waha', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $json, self::SECRET),
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
        ], $json);
    }

    /** @return array{Cabang, User, UserWhatsAppAccount} */
    private function account(): array
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'kota' => 'Bandung', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $account = UserWhatsAppAccount::create([
            'user_id' => $staff->getKey(),
            'cabang_id' => $branch->getKey(),
            'phone_e164' => '628777777777',
            'session_name' => 'user-'.$staff->getKey().'-bdg',
            'aktif' => true,
        ]);

        return [$branch, $staff, $account];
    }

    private function messagePayload(string $session, string $from, string $id): array
    {
        return [
            'event' => 'message',
            'session' => $session,
            'engine' => 'WEBJS',
            'payload' => [
                'id' => $id,
                'timestamp' => now()->timestamp,
                'from' => $from,
                'to' => '628777777777@c.us',
                'fromMe' => false,
                'body' => 'Halo, saya ingin bertanya program bimbel.',
                'pushName' => 'Calon Siswa',
                'hasMedia' => false,
            ],
        ];
    }

    private function gowsMessagePayload(string $session): array
    {
        return [
            'event' => 'message',
            'session' => $session,
            'engine' => 'GOWS',
            'payload' => [
                'id' => 'false_109719301685330@lid_gows-message',
                'timestamp' => now()->timestamp,
                'from' => '109719301685330@lid',
                'to' => null,
                'fromMe' => false,
                'body' => 'Halo dari GOWS.',
                'hasMedia' => false,
                '_data' => [
                    'Info' => [
                        'Chat' => '109719301685330@lid',
                        'SenderAlt' => '6285320008589:5@s.whatsapp.net',
                        'PushName' => 'Pengirim GOWS',
                    ],
                ],
            ],
        ];
    }

    private function leadData(Cabang $branch, User $staff, string $name, string $phone): array
    {
        return [
            'nama' => $name,
            'no_wa' => $phone,
            'cabang_id' => $branch->getKey(),
            'status_lead' => 'Cold',
            'diinput_oleh_user_id' => $staff->getKey(),
            'diserahkan_ke_user_id' => $staff->getKey(),
            'tanggal_masuk' => today(),
        ];
    }
}

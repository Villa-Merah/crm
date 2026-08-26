<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('no_wa_normalized', 20)->nullable()->after('no_wa')->index();
            $table->string('kanal_masuk', 30)->default('Manual')->after('sumber_lead')->index();
            $table->timestamp('whatsapp_first_seen_at')->nullable()->after('tanggal_masuk');
            $table->timestamp('whatsapp_last_seen_at')->nullable()->after('whatsapp_first_seen_at')->index();
        });

        DB::table('leads')->select(['lead_id', 'no_wa'])->orderBy('lead_id')->each(function (object $lead): void {
            $phone = preg_replace('/\D+/', '', (string) $lead->no_wa);
            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            } elseif (str_starts_with($phone, '8')) {
                $phone = '62'.$phone;
            }
            DB::table('leads')->where('lead_id', $lead->lead_id)->update([
                'no_wa_normalized' => preg_match('/^[1-9][0-9]{7,14}$/', $phone) === 1 ? $phone : null,
            ]);
        });

        Schema::create('user_whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->string('phone_e164', 20)->unique();
            $table->string('session_name', 100)->unique();
            $table->string('waha_me_id', 150)->nullable();
            $table->string('status', 30)->default('Disconnected')->index();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained('user_whatsapp_accounts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads', 'lead_id')->nullOnDelete();
            $table->string('chat_id', 180);
            $table->string('contact_phone_e164', 20)->nullable()->index();
            $table->string('contact_name', 150)->nullable();
            $table->string('match_status', 30)->default('Unresolved')->index();
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'chat_id'], 'wa_conversation_account_chat_unique');
            $table->index(['whatsapp_account_id', 'match_status'], 'wa_conversation_account_match_index');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained('user_whatsapp_accounts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads', 'lead_id')->nullOnDelete();
            $table->string('external_message_id', 220);
            $table->string('direction', 20)->index();
            $table->string('message_type', 50)->default('text');
            $table->text('body')->nullable();
            $table->boolean('has_media')->default(false);
            $table->string('media_path', 500)->nullable();
            $table->timestamp('sent_at')->index();
            $table->boolean('from_me')->default(false);
            $table->string('processing_status', 30)->default('Processed')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'external_message_id'], 'wa_message_account_external_unique');
            $table->index(['lead_id', 'sent_at']);
        });

        Schema::create('waha_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('payload_hash', 64)->unique();
            $table->string('event_name', 80)->index();
            $table->string('session_name', 100)->index();
            $table->string('status', 30)->default('Pending')->index();
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waha_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('user_whatsapp_accounts');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['no_wa_normalized', 'kanal_masuk', 'whatsapp_first_seen_at', 'whatsapp_last_seen_at']);
        });
    }
};

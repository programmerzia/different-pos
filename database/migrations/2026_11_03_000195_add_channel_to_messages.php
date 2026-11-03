<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp joins the message log: `channel` (sms | whatsapp) on `messages` and `sms_broadcasts`, and the
 * `handoff` status for click-to-chat (the text was opened in the user's own WhatsApp; delivery unknown).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('channel', 16)->default('sms')->after('customer_id');
            $table->index(['tenant_id', 'channel', 'created_at']);
        });
        Schema::table('sms_broadcasts', function (Blueprint $table): void {
            $table->string('channel', 16)->default('sms')->after('user_id');
        });
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_status_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_status_check CHECK (status IN ('queued','sent','failed','handoff'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_channel_check CHECK (channel IN ('sms','whatsapp'))");
        DB::statement("ALTER TABLE sms_broadcasts ADD CONSTRAINT sms_broadcasts_channel_check CHECK (channel IN ('sms','whatsapp'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sms_broadcasts DROP CONSTRAINT sms_broadcasts_channel_check');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_channel_check');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_status_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_status_check CHECK (status IN ('queued','sent','failed'))");
        Schema::table('sms_broadcasts', function (Blueprint $table): void {
            $table->dropColumn('channel');
        });
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'channel', 'created_at']);
            $table->dropColumn('channel');
        });
    }
};

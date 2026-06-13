<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The base `notification_messages` table is created by the iankibet/shbackend
     * package (slug, subject, mail, sms, action_label, action_url). Here we add the
     * extra columns this app needs for channel selection and a WhatsApp body.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notification_messages')) {
            return;
        }

        Schema::table('notification_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_messages', 'whatsapp')) {
                $table->longText('whatsapp')->nullable()->after('sms');
            }

            if (! Schema::hasColumn('notification_messages', 'channels')) {
                $table->json('channels')->nullable()->after('whatsapp');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_messages')) {
            return;
        }

        Schema::table('notification_messages', function (Blueprint $table) {
            foreach (['whatsapp', 'channels'] as $column) {
                if (Schema::hasColumn('notification_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

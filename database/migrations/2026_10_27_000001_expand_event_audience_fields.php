<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_requests', function (Blueprint $table) {
            $table->text('event_for')->nullable()->change();
            $table->text('attention_to')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_requests', function (Blueprint $table) {
            $table->string('event_for')->nullable()->change();
            $table->string('attention_to')->nullable()->change();
        });
    }
};

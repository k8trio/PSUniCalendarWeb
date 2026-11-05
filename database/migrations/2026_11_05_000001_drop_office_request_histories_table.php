<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('office_request_histories');
    }

    public function down(): void
    {
        Schema::create('office_request_histories', function (Blueprint $table) {
            $table->id();
            $table->string('office_email')->index();
            $table->foreignId('event_request_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('before_data')->nullable();
            $table->json('after_data');
            $table->boolean('is_undone')->default(false);
            $table->timestamps();

            $table->index(['office_email', 'is_undone', 'id']);
        });
    }
};

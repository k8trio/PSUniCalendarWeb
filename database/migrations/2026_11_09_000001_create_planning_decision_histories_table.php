<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_decision_histories', function (Blueprint $table) {
            $table->id();
            $table->string('planning_email')->index();
            $table->foreignId('event_request_id')->constrained()->cascadeOnDelete();
            $table->string('decision');
            $table->json('before_data');
            $table->json('after_data');
            $table->boolean('is_undone')->default(false);
            $table->timestamps();

            $table->index(['planning_email', 'is_undone', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_decision_histories');
    }
};

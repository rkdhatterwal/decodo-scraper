<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decodo_batches', function (Blueprint $table) {
            $table->id();

            // The batch has no single Decodo task_id; it groups multiple tasks.
            // We store a local identifier so our app can reference a logical batch.
            $table->string('name')->nullable()->comment('Optional human-readable label');

            // Number of URLs submitted in this batch.
            $table->unsignedInteger('total_tasks')->default(0);

            // Aggregate status: pending | done | faulted | partial
            // "partial" = some tasks done, some faulted.
            $table->string('status')->default('pending')->index();

            // Callback URL used for this batch (for reference / re-queue).
            $table->string('callback_url')->nullable();

            // Passthrough token echoed back by Decodo for verification.
            $table->string('passthrough')->nullable();

            // Shared options applied to all tasks in this batch (geo, headless, etc.)
            $table->json('options')->nullable();

            // Timestamps when first task was queued and last task completed.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decodo_batches');
    }
};

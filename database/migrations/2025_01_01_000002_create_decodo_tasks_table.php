<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decodo_tasks', function (Blueprint $table) {
            $table->id();

            // Decodo's own task identifier (returned on queue).
            $table->string('decodo_task_id')->unique()->index();

            // Nullable FK to a batch — null for standalone tasks.
            $table->foreignId('decodo_batch_id')
                ->nullable()
                ->constrained('decodo_batches')
                ->nullOnDelete();

            // Polymorphic relation: link this task back to any app model.
            // e.g. $product->decodoTasks() or $article->decodoTasks()
            $table->nullableMorphs('scrapeable');

            // The URL or query that was submitted.
            $table->text('url')->nullable();
            $table->text('query')->nullable();

            // Task status mirroring Decodo's values: pending | done | faulted
            $table->string('status')->default('pending')->index();

            // Full payload that was sent (for audit / retry).
            $table->json('payload')->nullable();

            // Options used: headless, geo, target, etc.
            $table->json('options')->nullable();

            // Callback URL for this task.
            $table->string('callback_url')->nullable();

            // Passthrough token for callback verification.
            $table->string('passthrough')->nullable();

            // Scraped result content stored locally (optional — can be large).
            $table->longText('result_content')->nullable();

            // HTTP status code of the upstream page.
            $table->unsignedSmallInteger('result_status_code')->nullable();

            // Raw callback payload received from Decodo webhook.
            $table->json('webhook_payload')->nullable();

            // Timestamps for lifecycle tracking.
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decodo_tasks');
    }
};

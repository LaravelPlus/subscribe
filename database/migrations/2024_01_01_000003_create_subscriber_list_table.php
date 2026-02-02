<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_list', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('subscribers')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('subscription_lists')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subscriber_id', 'list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_list');
    }
};

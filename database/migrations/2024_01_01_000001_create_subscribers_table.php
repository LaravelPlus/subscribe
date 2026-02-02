<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->json('attributes')->nullable();
            $table->json('tags')->nullable();
            $table->string('source')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_token')->nullable()->index();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable()->index();
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};

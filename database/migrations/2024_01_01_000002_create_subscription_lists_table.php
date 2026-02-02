<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('double_opt_in')->default(true);
            $table->boolean('welcome_email_enabled')->default(false);
            $table->string('welcome_email_subject')->nullable();
            $table->text('welcome_email_content')->nullable();
            $table->string('confirmation_email_subject')->nullable();
            $table->text('confirmation_email_content')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_lists');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only: the uuid columns used by HasUuid are added by the host app's own
// migration, so the standalone suite adds them here.
return new class() extends Migration
{
    public function up(): void
    {
        foreach (['subscribers', 'subscription_lists'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->uuid('uuid')->nullable()->unique());
        }
    }
};

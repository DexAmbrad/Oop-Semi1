<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('password')->index();
            $table->string('avatar_path')->nullable()->after('role');
            $table->string('headline')->nullable()->after('avatar_path');
            $table->string('phone', 40)->nullable()->after('headline');
            $table->string('status')->default('active')->after('phone')->index();
            $table->timestamp('last_seen_at')->nullable()->after('status');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'avatar_path', 'headline', 'phone', 'status', 'last_seen_at', 'deleted_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('title');
            $table->string('subject')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('room_code', 12)->unique();
            $table->string('accent', 24)->default('violet');
            $table->string('icon', 24)->default('book');
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('term')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedSmallInteger('capacity')->default(50);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('type')->default('assignment')->index();
            $table->unsignedInteger('max_points')->default(100);
            $table->timestamp('due_at')->nullable()->index();
            $table->boolean('allow_late')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

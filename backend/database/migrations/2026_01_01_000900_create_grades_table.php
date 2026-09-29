<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type')->default('assignment')->index();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_title');
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(1);
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id', 'item_type', 'item_id'], 'grades_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};

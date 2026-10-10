<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('book_title');
            $table->string('accession_no', 40)->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->timestamp('fine_settled_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'returned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_loans');
    }
};

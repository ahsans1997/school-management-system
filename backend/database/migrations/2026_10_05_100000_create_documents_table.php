<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One documents table for every record that keeps files (students now,
     * teachers next, admission applications later), replacing
     * student_documents. Owners are addressed by morph alias ("student", …).
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');                // owner (alias + id), indexed
            $table->string('title', 100);
            $table->string('path');                         // private disk
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('documents')->insertUsing(
            ['documentable_type', 'documentable_id', 'title', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at'],
            DB::table('student_documents')->selectRaw(
                "'student', student_id, title, path, original_name, mime_type, size, uploaded_by, created_at, updated_at"
            ),
        );

        Schema::drop('student_documents');
    }

    public function down(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('student_documents')->insertUsing(
            ['student_id', 'title', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at'],
            DB::table('documents')->where('documentable_type', 'student')->select(
                'documentable_id', 'title', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at',
            ),
        );

        Schema::drop('documents');
    }
};

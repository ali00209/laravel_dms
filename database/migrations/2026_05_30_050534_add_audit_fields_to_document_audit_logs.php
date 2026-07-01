<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_audit_logs')) {
            Schema::table('document_audit_logs', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete()->after('id');
                $table->string('action')->nullable()->after('event');
                $table->string('old_status')->nullable()->after('action');
                $table->string('new_status')->nullable()->after('old_status');
                $table->json('metadata')->nullable()->after('diff');
                $table->index('organization_id');
            });
        } else {
            Schema::create('document_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('document_type')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('event')->nullable();
                $table->string('action')->nullable();
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('diff')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->index('organization_id');
                $table->index(['document_type', 'document_id']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_policy_revisions', function (Blueprint $table) {
            $table->id()->comment('불변 정책 revision ID');
            $table->foreignId('policy_id')->comment('정책 ID')->constrained('glitter_policy_policies')->restrictOnDelete();
            $table->unsignedInteger('version')->comment('정책별 증가 게시 버전');
            $table->json('definition')->comment('게시 시점 JSON Rule DSL 스냅샷');
            $table->char('definition_hash', 64)->comment('정규화된 정의 SHA-256');
            $table->unsignedBigInteger('published_by')->nullable()->comment('게시 관리자 ID');
            $table->timestamp('published_at')->comment('게시 일시');
            $table->timestamps();

            $table->unique(['policy_id', 'version'], 'glitter_policy_revision_version_unique');
            $table->index(['policy_id', 'published_at'], 'glitter_policy_revision_published_index');
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_policy_revisions', fn (Blueprint $table) => $table->comment('Glitter 불변 정책 revision'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_policy_revisions');
    }
};

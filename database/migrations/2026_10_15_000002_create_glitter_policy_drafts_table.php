<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_policy_drafts', function (Blueprint $table) {
            $table->id()->comment('정책 초안 ID');
            $table->foreignId('policy_id')->unique()->comment('정책 ID')->constrained('glitter_policy_policies')->restrictOnDelete();
            $table->json('definition')->comment('검증 전후 JSON Rule DSL 정의');
            $table->unsignedBigInteger('lock_version')->default(0)->comment('낙관적 잠금 버전');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('마지막 수정 관리자 ID');
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_policy_drafts', fn (Blueprint $table) => $table->comment('Glitter 정책 수정 가능 초안'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_policy_drafts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_policy_policies', function (Blueprint $table) {
            $table->id()->comment('정책 ID');
            $table->uuid('uuid')->unique()->comment('외부 노출용 정책 UUID');
            $table->string('owner_extension', 100)->comment('정책 소유 확장 식별자');
            $table->string('policy_key', 150)->comment('소유 확장 내부 정책 키');
            $table->text('description')->nullable()->comment('정책 설명');
            $table->string('status', 20)->default('draft')->comment('정책 상태 (draft: 초안, published: 게시, disabled: 비활성)');
            $table->unsignedInteger('published_version')->nullable()->comment('현재 게시 revision 버전');
            $table->unsignedBigInteger('created_by')->nullable()->comment('생성 관리자 ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('수정 관리자 ID');
            $table->timestamps();

            $table->unique(['owner_extension', 'policy_key'], 'glitter_policy_owner_key_unique');
            $table->index(['status', 'updated_at'], 'glitter_policy_status_updated_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_policy_policies', fn (Blueprint $table) => $table->comment('Glitter 정책 레지스트리'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_policy_policies');
    }
};

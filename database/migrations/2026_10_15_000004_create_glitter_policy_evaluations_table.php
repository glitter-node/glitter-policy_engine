<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glitter_policy_evaluations', function (Blueprint $table) {
            $table->id()->comment('평가 이력 ID');
            $table->uuid('evaluation_uuid')->unique()->comment('외부 노출용 평가 UUID');
            $table->unsignedBigInteger('policy_id')->nullable()->comment('평가 정책 ID 스냅샷 연결');
            $table->unsignedBigInteger('revision_id')->nullable()->comment('평가한 불변 revision ID');
            $table->string('owner_extension', 100)->comment('정책 소유 확장 식별자 스냅샷');
            $table->string('policy_key', 150)->comment('정책 키 스냅샷');
            $table->unsignedInteger('policy_version')->nullable()->comment('평가한 게시 버전');
            $table->string('decision', 20)->comment('판정 (allow, deny, indeterminate)');
            $table->string('reason_code', 80)->comment('기계 판독용 판정 사유 코드');
            $table->string('action', 150)->comment('평가 요청 action');
            $table->json('evaluation_metadata')->nullable()->comment('민감 원문을 제외한 규칙 일치 및 입력 형태 메타데이터');
            $table->unsignedBigInteger('duration_us')->comment('평가 소요 시간 (마이크로초)');
            $table->unsignedBigInteger('requested_by')->nullable()->comment('평가 요청 관리자 ID');
            $table->timestamp('evaluated_at')->comment('평가 일시');
            $table->timestamp('created_at')->useCurrent()->comment('이력 생성 일시');

            $table->index(['owner_extension', 'policy_key', 'evaluated_at'], 'glitter_policy_eval_identifier_time_index');
            $table->index(['decision', 'evaluated_at'], 'glitter_policy_eval_decision_time_index');
            $table->foreign('policy_id')->references('id')->on('glitter_policy_policies')->restrictOnDelete();
            $table->foreign('revision_id')->references('id')->on('glitter_policy_revisions')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_policy_evaluations', fn (Blueprint $table) => $table->comment('Glitter 정책 평가 최소 이력'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glitter_policy_evaluations');
    }
};

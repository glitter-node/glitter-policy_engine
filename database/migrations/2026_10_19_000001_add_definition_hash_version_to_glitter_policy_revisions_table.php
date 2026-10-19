<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glitter_policy_revisions', function (Blueprint $table) {
            $table->unsignedInteger('definition_hash_version')
                ->default(1)
                ->comment('정의 해시 알고리즘 버전');
        });
    }

    public function down(): void
    {
        Schema::table('glitter_policy_revisions', function (Blueprint $table) {
            $table->dropColumn('definition_hash_version');
        });
    }
};

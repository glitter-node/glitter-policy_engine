<?php

use Illuminate\Support\Facades\Route;
use Modules\Glitter\PolicyEngine\Http\Controllers\Admin\EvaluationController;
use Modules\Glitter\PolicyEngine\Http\Controllers\Admin\PolicyController;

/*
| ModuleRouteServiceProvider applies:
| URL prefix  api/modules/glitter-policy_engine
| Name prefix api.modules.glitter-policy_engine.
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:600,1'])->name('admin.')->group(function () {
    Route::prefix('policies')->name('policies.')->group(function () {
        Route::get('/', [PolicyController::class, 'index'])
            ->middleware('permission:admin,glitter-policy_engine.policies.read')->name('index');
        Route::post('/', [PolicyController::class, 'store'])
            ->middleware('permission:admin,glitter-policy_engine.policies.create')->name('store');
        Route::get('/{policy}/diff', [PolicyController::class, 'diff'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.read')->name('diff');
        Route::get('/{policy}', [PolicyController::class, 'show'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.read')->name('show');
        Route::patch('/{policy}', [PolicyController::class, 'update'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.update')->name('update');
        Route::put('/{policy}/draft', [PolicyController::class, 'updateDraft'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.update')->name('draft.update');
        Route::post('/{policy}/validate', [PolicyController::class, 'validateDraft'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.update')->name('validate');
        Route::post('/{policy}/publish', [PolicyController::class, 'publish'])
            ->whereNumber('policy')->middleware('permission:admin,glitter-policy_engine.policies.publish')->name('publish');
    });

    Route::prefix('evaluations')->name('evaluations.')->group(function () {
        Route::get('/', [EvaluationController::class, 'index'])
            ->middleware('permission:admin,glitter-policy_engine.evaluations.read')->name('index');
        Route::post('/simulate', [EvaluationController::class, 'simulate'])
            ->middleware('permission:admin,glitter-policy_engine.evaluations.simulate')->name('simulate');
    });
});

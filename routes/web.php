<?php

declare(strict_types=1);

use Core\Commerce\Controllers\MatrixTrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Commerce Matrix Routes
|--------------------------------------------------------------------------
*/

Route::prefix('commerce')->name('commerce.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Permission Matrix Training Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('matrix')->name('matrix.')->group(function () {
        // Training submission (POST form from train-prompt view)
        Route::post('/train', [MatrixTrainingController::class, 'train'])
            ->name('train');

        // Pending requests view
        Route::get('/pending', [MatrixTrainingController::class, 'pending'])
            ->name('pending');

        // Bulk training
        Route::post('/bulk-train', [MatrixTrainingController::class, 'bulkTrain'])
            ->name('bulk-train');
    });

});

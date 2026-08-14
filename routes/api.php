<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): array {
        return [
            'status' => 'ok',
            'service' => 'plataforma-ges-api',
        ];
    });
});

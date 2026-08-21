<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EstadisticasController;
use App\Http\Controllers\Api\ExpedienteController;
use App\Http\Controllers\Api\OnlyOfficeController;
use App\Http\Controllers\Api\ResolucionController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 intentos por minuto
Route::post('/contacto', [ContactController::class, 'store'])->middleware('throttle:10,1'); // 10 mensajes por minuto

// ONLYOFFICE Document Server cannot use a browser JWT. These URLs are
// temporary-signed by Laravel; callbacks additionally require ONLYOFFICE JWT.
Route::get('/onlyoffice/document/{type}/{id}', [OnlyOfficeController::class, 'document'])
    ->name('onlyoffice.document');
Route::post('/onlyoffice/callback/{type}/{id}', [OnlyOfficeController::class, 'callback'])
    ->name('onlyoffice.callback');

// Protected routes (require JWT)
Route::middleware('auth:api')->group(function () {
    // Auth endpoints
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Expedientes endpoints
    Route::apiResource('expedientes', ExpedienteController::class);
    Route::post('/expedientes/{id}/archivo', [ExpedienteController::class, 'uploadFile']);
    Route::get('/expedientes/{id}/archivo/download', [ExpedienteController::class, 'downloadFile']);
    Route::get('/onlyoffice/config/{type}/{id}', [OnlyOfficeController::class, 'config']);
    Route::post('/expedientes/{id}/pdf-master/reintentar', [OnlyOfficeController::class, 'retryMasterPdf']);
    Route::post('/onlyoffice/session/{type}/{id}/heartbeat', [OnlyOfficeController::class, 'heartbeat']);

    // Resoluciones endpoints
    Route::get('/expedientes/{id}/resoluciones', [ResolucionController::class, 'index']);
    Route::post('/expedientes/{id}/resoluciones/confirmar-inicial', [ResolucionController::class, 'confirmarInicial']);
    Route::post('/expedientes/{id}/resoluciones/siguiente', [ResolucionController::class, 'siguiente']);
    Route::get('/expedientes/{id}/resoluciones/{resolucionId}/download', [ResolucionController::class, 'descargar']);
    Route::post('/expedientes/{id}/resoluciones/{resolucionId}/completar', [ResolucionController::class, 'completar']);
    Route::post('/expedientes/{id}/resoluciones/{resolucionId}/completar-online', [ResolucionController::class, 'completarOnline']);

    // Documentos endpoints
    Route::get('/expedientes/{id}/word', [DocumentoController::class, 'generateWord']);
    Route::get('/expedientes/{id}/pdf', [DocumentoController::class, 'generatePdf']);

    // Estadisticas endpoints
    Route::get('/estadisticas', [EstadisticasController::class, 'index']);
    Route::get('/estadisticas/expedientes-por-estado', [EstadisticasController::class, 'expedientesPorEstado']);
    Route::get('/estadisticas/expedientes-por-tipo', [EstadisticasController::class, 'expedientesPorMateria']);

    // Admin only routes
    Route::middleware('role:ADMIN')->group(function () {
        Route::apiResource('usuarios', UsuarioController::class);
        Route::get('/contacto', [ContactController::class, 'index']);
        Route::get('/contacto/{id}', [ContactController::class, 'show']);
        Route::delete('/contacto/{id}', [ContactController::class, 'destroy']);
    });
});

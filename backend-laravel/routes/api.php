<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EstadisticasController;
use App\Http\Controllers\Api\ExpedienteController;
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

    // Resoluciones endpoints
    Route::get('/expedientes/{id}/resoluciones', [ResolucionController::class, 'index']);
    Route::post('/expedientes/{id}/resoluciones/confirmar-inicial', [ResolucionController::class, 'confirmarInicial']);
    Route::post('/expedientes/{id}/resoluciones/siguiente/editor', [ResolucionController::class, 'iniciarEditor']);
    Route::post('/expedientes/{id}/resoluciones/siguiente', [ResolucionController::class, 'siguiente']);
    Route::get('/expedientes/{id}/resoluciones/{resolucionId}/editor', [ResolucionController::class, 'editor']);
    Route::put('/expedientes/{id}/resoluciones/{resolucionId}/editor', [ResolucionController::class, 'guardarEditor']);
    Route::post('/expedientes/{id}/resoluciones/{resolucionId}/finalizar-editor', [ResolucionController::class, 'finalizarEditor']);
    Route::get('/expedientes/{id}/resoluciones/{resolucionId}/download', [ResolucionController::class, 'descargar']);
    Route::post('/expedientes/{id}/resoluciones/{resolucionId}/completar', [ResolucionController::class, 'completar']);

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

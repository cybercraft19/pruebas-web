<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\IntentoController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PruebaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/intentos/{intento}', [IntentoController::class, 'show']);
    Route::get('/pruebas-publicadas', [PruebaController::class, 'publicadas']);

    Route::middleware('role:evaluador')->group(function () {
        Route::get('/estudiantes', [EstudianteController::class, 'index']);
        Route::post('/estudiantes', [EstudianteController::class, 'store']);
        Route::get('/estudiantes/exportar', [EstudianteController::class, 'exportar']);
        Route::delete('/estudiantes/{estudiante}', [EstudianteController::class, 'destroy']);
        Route::post('/estudiantes/{estudiante}/reset-password', [EstudianteController::class, 'resetPassword']);

        Route::get('/pruebas', [PruebaController::class, 'index']);
        Route::get('/pruebas/{prueba}', [PruebaController::class, 'show']);
        Route::put('/pruebas/{prueba}', [PruebaController::class, 'update']);
        Route::post('/pruebas/{prueba}/publicar', [PruebaController::class, 'publicar']);
        Route::post('/pruebas/{prueba}/archivar', [PruebaController::class, 'archivar']);
        Route::get('/pruebas/{prueba}/resultados', [PruebaController::class, 'resultados']);
        Route::get('/pruebas/{prueba}/resultados/exportar', [PruebaController::class, 'exportarResultados']);
        Route::post('/intentos/{intento}/firmar', [IntentoController::class, 'firmar']);
    });

    Route::middleware('role:estudiante')->group(function () {
        Route::get('/mis-intentos', [IntentoController::class, 'mios']);
        Route::post('/intentos', [IntentoController::class, 'store']);
        Route::post('/intentos/{intento}/respuestas', [IntentoController::class, 'responder']);
        Route::post('/intentos/{intento}/tmt', [IntentoController::class, 'registrarTmt']);
        Route::post('/intentos/{intento}/finalizar', [IntentoController::class, 'finalizar']);
    });
});

<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\IntentoController;
use App\Http\Controllers\Api\PruebaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/intentos/{intento}', [IntentoController::class, 'show']);
    Route::get('/pruebas-publicadas', [PruebaController::class, 'publicadas']);

    Route::middleware('role:evaluador')->group(function () {
        Route::get('/estudiantes', [EstudianteController::class, 'index']);
        Route::post('/estudiantes', [EstudianteController::class, 'store']);

        Route::get('/pruebas/plantilla', [PruebaController::class, 'plantilla']);
        Route::post('/pruebas/importar', [PruebaController::class, 'importar']);
        Route::get('/pruebas', [PruebaController::class, 'index']);
        Route::get('/pruebas/{prueba}', [PruebaController::class, 'show']);
        Route::post('/pruebas/{prueba}/publicar', [PruebaController::class, 'publicar']);
        Route::get('/pruebas/{prueba}/resultados', [PruebaController::class, 'resultados']);
    });

    Route::middleware('role:estudiante')->group(function () {
        Route::get('/mis-intentos', [IntentoController::class, 'mios']);
        Route::post('/intentos', [IntentoController::class, 'store']);
        Route::post('/intentos/{intento}/respuestas', [IntentoController::class, 'responder']);
        Route::post('/intentos/{intento}/finalizar', [IntentoController::class, 'finalizar']);
    });
});

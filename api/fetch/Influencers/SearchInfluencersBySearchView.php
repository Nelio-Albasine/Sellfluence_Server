<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1); // Ativa registro de erros em arquivo
ini_set('error_log', __DIR__ . '/../../logs/home/error_GetHomeInfluencers.log');
header('Content-Type: application/json; charset=utf-8');


if ($_SERVER['REQUEST_METHOD'] == "GET") {
    try {
        //code...
    } catch (\Throwable $th) {
        error_log("Erro na execução: " . $e->getMessage());
        error_log("Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
        error_log("Trace completo: " . $e->getTraceAsString());
    }
} else {
    error_log("Invalid request method");
}

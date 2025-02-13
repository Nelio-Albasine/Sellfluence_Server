<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['estados']) || empty($_GET['estados'])) {
    echo json_encode(["erro" => "Nenhum estado foi selecionado."]);
    exit;
}

$estados = explode(',', $_GET['estados']); 
$cidades = [];

$baseUrl = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/";

foreach ($estados as $estado) {
    $url = $baseUrl . $estado . "/municipios";
    $response = file_get_contents($url);
    if ($response !== false) {
        $municipios = json_decode($response, true);
        foreach ($municipios as $municipio) {
           
            $cidades[] = $municipio['nome'];
        }
    }
}

echo json_encode($cidades, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '../connection.php';
$conn = getDatabaseConnection();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Atualiza a subcategoria "Moda" para adicionar um novo valor
    $novaSubCategoria = 'Nelio'; // Novo valor para adicionar
    $stmt = $conn->prepare("UPDATE Tags SET SubCategoria = CONCAT(SubCategoria, ', ".$novaSubCategoria."') WHERE Categoria = 'Moda'");
    $stmt->execute();

    $stmt->close();
    $conn->close();
    
    echo "Campo atualizado com sucesso.";
} catch (mysqli_sql_exception $e) {
    echo "Erro ao atualizar campo: " . $e->getMessage();
}
?>

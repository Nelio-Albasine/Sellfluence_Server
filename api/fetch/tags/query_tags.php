<?php
/*
 * http://localhost/Selfluence/api/fetch/tags/query_tags.php?action=all
 * http://localhost/Selfluence/api/fetch/tags/query_tags.php?action=categories
 * http://localhost/Selfluence/api/fetch/tags/query_tags.php?action=specific&category=Moda
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '../../conn/Wamp64Connection.php';
$conn = getWamp64Connection("Sellfluence_Public");

// Verificar qual parâmetro foi passado
if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(array('error' => 'The "action" parameter is required.'));
    exit();
}

$action = $_GET['action'];

try {
    if ($action === 'all') {
        // Retornar todas as categorias e suas subtags
        $sql = "SELECT Categoria, SubCategoria FROM Tags";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $tags = array();
        while ($row = $result->fetch_assoc()) {
            $tags[$row['Categoria']][] = $row['SubCategoria'];
        }
        echo json_encode($tags, JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'categories') {
        // Retornar apenas as categorias (sem subtags)
        $sql = "SELECT DISTINCT Categoria FROM Tags";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $categorias = array();
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row['Categoria'];
        }
        echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'specific' && isset($_GET['category'])) {
        // Retornar categoria específica com suas subtags
        $categoria = $_GET['category'];
        $sql = "SELECT SubCategoria FROM Tags WHERE Categoria = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $categoria);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $subcategorias = array();
            while ($row = $result->fetch_assoc()) {
                $subcategorias[] = $row["SubCategoria"];
            }
            echo json_encode(array($categoria => $subcategorias), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array("error" => "No subcategories found for the category '$categoria'"), JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Se o parâmetro de "action" for inválido ou não especificado corretamente
        http_response_code(400);
        echo json_encode(array('error' => 'Invalid action or missing "category" parameter.'));
    }

    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(array('error' => 'Error processing the request: ' . $e->getMessage()));
}

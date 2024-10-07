<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '../connection.php';
$conn = getDatabaseConnection();

if(!isset($_GET['categoria'])) {
    http_response_code(400); // Bad Request
    echo json_encode(array('error' => 'The "categoria" parameter is required.'));
    exit();
}

$categoria = $_GET['categoria'];

$sql = "SELECT SubCategoria FROM Tags WHERE Categoria = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $categoria);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $subcategorias = array(); 
    while($row = $result->fetch_assoc()) {
        $subcategorias[] = $row["SubCategoria"];
    }
    echo json_encode(array($categoria => $subcategorias), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array("error" => "0 resultados"), JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>

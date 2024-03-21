<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');


try {
    require 'connection.php';
    $conn = getDatabaseConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['name']) && isset($data['email']) && isset($data['id'])) {
        $user_name = $data['name'];
        $user_email = $data['email'];
        $user_id = $data['id'];

        //Add to DB
        // Placeholder for database operation
        echo json_encode(["success" => true, "message" => "Parabéns! A requisição foi feita com sucesso!"]);

    } else {
        echo json_encode(["success" => false, "message" => "Alguns campos enviados estão vazios!"]);
    }

    $conn->close();
} catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro no servidor."]);
}

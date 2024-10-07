<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

ini_set('error_log', __DIR__ . '/error_updating_unreadMessages.log');

function updateAllUnreadMessages($conn, $chatId, $messagesIds): array {
    $allMessagesIds = json_decode($messagesIds, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erro ao decodificar messagesIds: " . json_last_error_msg());
        return ['error' => 'Invalid messagesIds format'];
    }

    $response = [];
    
    foreach ($allMessagesIds as $messageId) {
        $sql = "UPDATE messages SET readStatus = 1 WHERE chatId = ? AND messageId = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Erro ao preparar a consulta SQL: " . $conn->error);
            $response[] = [
                'messageId' => $messageId,
                'status' => 'Erro na preparação da query'
            ];
            continue;
        }
        
        $stmt->bind_param('ii', $chatId, $messageId);
        if ($stmt->execute()) {
            $response[] = [
                'messageId' => $messageId,
                'status' => 'Success'
            ];
        } else {
            error_log("Erro ao executar a query: " . $stmt->error);
            $response[] = [
                'messageId' => $messageId,
                'status' => 'Erro ao atualizar o status de leitura'
            ];
        }
        $stmt->close();
    }

    return $response;
}


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    require_once "Wamp64Connection.php";
    $conn = getMySQLConnection("conversations");

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['chatId']) || empty($data['chatId']) || !isset($data['messagesIds']) || empty($data['messagesIds'])) {
        error_log("Alguns dos parâmetros do POST estão ausentes!");
        echo json_encode(['error' => 'Missing chatId or messagesIds']);
        exit;
    }

    $response = updateAllUnreadMessages($conn, $data["chatId"], json_encode($data["messagesIds"]));

    echo json_encode($response);
} else {
    die("The request method is not POST");
}

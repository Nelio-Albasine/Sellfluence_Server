<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

ini_set('error_log', __DIR__ . '/error_getingAllMessages.log');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once "Wamp64Connection.php";
    $conn = getWamp64Connection("conversations");

    if ($conn) {
        if (isset($_GET['chatId'])) {
            $chatId = intval($_GET['chatId']);

            $messages = getMessagesForChatId($conn, $chatId);

            
            $messagesArray = [
                'chatId' => $chatId,
                'messages' => $messages
            ];

            error_log("Response returned is: " . print_r($messagesArray, true));
            echo json_encode($messagesArray);
        } else {
            echo json_encode(['error' => 'Parâmetro chatId não informado']);
        }

        $conn->close();
    } else {
        echo json_encode(['error' => 'Erro ao conectar ao banco de dados'], JSON_PRETTY_PRINT);
    }
} else {
    echo json_encode(['error' => 'Método não permitido'], JSON_PRETTY_PRINT);
}


function getMessagesForChatId($conn, $chatId)
{
    $sql = "SELECT * FROM Messages WHERE chatId = ? ORDER BY timestamp ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'messageId' => $row['messageId'],
            'chatId' => $row['chatId'],
            'message' => $row['text'] ?: "null",
            'timestamp' => $row['timestamp'],
            'isSent' => $row['isSent'] == 1 ? true : false,  
            'isEdited' => $row['isEdited'] == 1 ? true : false ,  
            'readStatus' => $row['readStatus'] == 1 ? true : false,  
            'isProposalMessage' => $row['isProposalMessage'] == 1 ? true : false, 
            'fileUrl' => $row['fileUrl'] ?: "null"
        ];
    }

    $stmt->close();
    return $messages;
}


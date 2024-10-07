<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function logMessage($message)
{
    error_log($message);
}


if (file_exists('/home/parceria/sellfluence.com.br/connection.php')) {
    logMessage('connection.php found');
    require ('/home/parceria/sellfluence.com.br/connection.php');
} else {
    logMessage('Arquivo connection.php não encontrado.');
    die('Arquivo connection.php não encontrado.');
}

$conn = getDatabaseConnection();

$createTBChat = "CREATE TABLE IF NOT EXISTS Chats(
        chatId VARCHAR(255) PRIMARY KEY,
        senderId VARCHAR(255),
        receiverId VARCHAR(255),
        conversation JSON
    )";
if ($conn->query($createTBChat) !== TRUE) {
    logMessage("Erro ao criar a tabela Notifications: " . $conn->error);
    die("Erro ao criar a tabela Notifications: " . $conn->error);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);

    $requiredFields = ['senderId', 'receiverId', 'text', 'file'];
    $missingFields = array_filter($requiredFields, function ($field) use ($data) {
        return empty($data[$field]);
    });

    if ($missingFields) {
        logMessage("Missing fields: " . implode(', ', $missingFields));
        echo json_encode(["success" => false, "message" => "Campos obrigatórios faltando: " . implode(', ', $missingFields)]);
        exit;
    }
   
    $idOfWhoSent = $data['senderId'];
    $receiverId = $data['receiverId'];
    $text = $data['textMessage'];
    $fileURL = $data['fileURL'];
    $timestamp = round(microtime(true) * 1000);


    $response = array(
        "conversations" => null,
        "errorOcurred" => false,
        "logMessage" => null
    );

    function generateChatId($senderId, $receiverId) {
        $separator = "_sprt_";
        return $senderId . $separator . $receiverId;
    }
    $generatedChatId = generateChatId($idOfWhoSent, $receiverId);

    $stmtGetAtualChat = $conn->prepare("SELECT * FROM Chats WHERE chatId = ?");
    if ($stmtGetAtualChat === false) {
        die('prepare() failed: ' . htmlspecialchars($conn->error));
    }
    $stmtGetAtualChat->bind_param("s", $generatedChatId);
    $stmtGetAtualChat->execute();
    $result = $stmtGetAtualChat->get_result();
    
    if ($result->num_rows > 0) {
        function updateExistingChat($conn, $notificationId, $senderId, $receiverId, $proposalItems, $timestamp, $text, $fileURL)
        {
            $chatIdGeneratedByConcat = generateChatId($senderId, $receiverId);
            $stmtGetAtualChat = $conn->prepare("SELECT id, conversation FROM Chats WHERE chatId = ?");
            if ($stmtGetAtualChat === false) {
                throw new Exception("prepare() failed: " . $conn->error);
            }

            $stmtGetAtualChat->bind_param("s", $chatIdGeneratedByConcat);
            if (!$stmtGetAtualChat->execute()) {
                throw new Exception("execute() failed: " . $conn->error);
            }

            $result = $stmtGetAtualChat->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $messageId = $row['id'] + 1;
                $currentChat = json_decode($row['conversation'], true);

                $newMessage = array(
                    "message" => array(
                        "whoSent" => $senderId,
                        "id" => $messageId,
                        "isProposalChat" => false,
                        "data" => array(
                            "notificationId" => $notificationId,
                            "customProposalInfo" => $proposalItems,
                        ),
                        "timestamp" => $timestamp,
                        "textMessage" => $text,
                        "fileURL" => $fileURL,
                    ),
                );
                $currentChat[] = $newMessage;
                $newConversationJSON = json_encode($currentChat);

                $stmtUpdateChat = $conn->prepare("UPDATE Chats SET conversation = ? WHERE chatId = ?");
                if ($stmtUpdateChat === false) {
                    throw new Exception("prepare() failed: " . $conn->error);
                }

                $stmtUpdateChat->bind_param("ss", $newConversationJSON, $chatIdGeneratedByConcat);

                if (!$stmtUpdateChat->execute()) {
                    throw new Exception("execute() failed: " . $stmtUpdateChat->error);
                } else {
                    return true;
                }
            } else {
                logMessage("No chat found with this id: " . $chatIdGeneratedByConcat);
                return false;
            }
        }

        $updateChats = updateExistingChat(
            $conn,
            $null,
            $senderId,
            $receiverId,
            null,
            $timestamp,
            $text,
            $fileURL
        );
        if ($updateChats) {
            
        } else {
            # code...
        }
    } else {
        $response = array(
            "errorOcurred" => true,
            'logMessage' => "Voce nao tem permissao para editar ou esse chat nao existe"
        );
        echo json_encode($response);
    }
} else {

}


$conn->close();
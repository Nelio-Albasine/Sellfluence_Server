<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

try {
    function logMessage($message)
    {
        error_log($message);
    }

    function generateChatId($senderId, $receiverId)
    {
        $separator = "_sprt_";
        return $senderId . $separator . $receiverId;
    }


    function updateOrCreateNewChat(
        $conn,
        $senderId,
        $notificationId,
        $receiverId,
        $proposalItems,
        $timestamp,
        $text,
        $fileURL
    ) {
        // Criação da tabela Chats se ela não existir
        $createTBChat = "CREATE TABLE IF NOT EXISTS Chats (
            chatId VARCHAR(255) PRIMARY KEY,
            senderId VARCHAR(255),
            receiverId VARCHAR(255),
            conversation JSON
        )";

        if ($conn->query($createTBChat) !== TRUE) {
            throw new Exception("Erro ao criar a tabela Chats: " . $conn->error);
        }

        function createNewChatIntoDB($conn, $notificationId, $senderId, $receiverId, $proposalItems, $timestamp)
        {
            $generatedChatId = generateChatId($senderId, $receiverId);

            // Preparação do statement para inserção de dados na tabela Chats
            $stmtInsertChat = $conn->prepare("INSERT INTO Chats (chatId, senderId, receiverId, conversation) VALUES (?, ?, ?, ?)");

            if (!$stmtInsertChat) {
                throw new Exception("Erro ao preparar o statement: " . $conn->error);
            }

            // Criação do array de mensagem com os novos campos
            $chatStarterStructure = array(
                "message" => array(
                    "whoSent" => $senderId,
                    "id" => 0,
                    "isProposalChat" => true,
                    "data" => array(
                        "notificationId" => $notificationId,
                        "customProposalInfo" => $proposalItems,
                    ),
                    "timestamp" => $timestamp,
                    "text" => null,
                    "file" => null,
                ),
            );
            $conversation = json_encode($chatStarterStructure);

            // Bind dos parâmetros para o statement
            $stmtInsertChat->bind_param("ssss", $generatedChatId, $senderId, $receiverId, $conversation);

            // Execução do statement
            if ($stmtInsertChat->execute()) {
                return true;
            } else {
                throw new Exception("Erro ao inserir os dados: " . $stmtInsertChat->error);
            }
        }

        function updateExistingChat(
            $conn,
            $notificationId,
            $senderId,
            $receiverId,
            $proposalItems,
            $timestamp,
            $text,
            $fileURL
        ) {
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
                        "isProposalChat" => true,
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

        $generatedChatId = generateChatId($senderId, $receiverId);
        $stmtGetAtualChat = $conn->prepare("SELECT * FROM Chats WHERE chatId = ?");
        if ($stmtGetAtualChat === false) {
            throw new Exception("prepare() failed: " . $conn->error);
        }
        $stmtGetAtualChat->bind_param("s", $generatedChatId);
        $stmtGetAtualChat->execute();
        $result = $stmtGetAtualChat->get_result();

        if ($result->num_rows > 0) {
            return updateExistingChat($conn, $notificationId, $senderId, $receiverId, $proposalItems, $timestamp, $text, $fileURL);
        } else {
            return createNewChatIntoDB($conn, $notificationId, $senderId, $receiverId, $proposalItems, $timestamp);
        }
    }

} catch (Exception $e) {
    logMessage("Ocorreu um erro: " . $e->getMessage());
    echo json_encode(array("error" => $e->getMessage()));
} finally {
    $conn->close();
}
?>
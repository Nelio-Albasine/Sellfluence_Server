<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

ini_set('error_log', __DIR__ . '/error_getAllChat.log');

function loadAllMyChats($conn, $participant)
{
    $chats = [];
    $query = "SELECT * FROM Chats WHERE participant_1 = ? OR participant_2 = ? ORDER BY updatedAt DESC ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ss", $participant, $participant);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $otherParticipant = $row["participant_1"] == $participant ? $row["participant_2"] : $row["participant_1"];
            $chatId = $row["chatId"];

            require_once "./Chat_List_Inclusions/IncludeFromMessages.php";
            $messagesInfo = getFromMessages($conn, $chatId);

            require_once "./Chat_List_Inclusions/IncludeFromUserProfile.php";
            $nameAndProfile = getFromUserProfile($otherParticipant);

            // echo (print_r($messagesInfo, true));

            $chat = [
                "chatId" => $chatId,
                "otherParticipantId" => $otherParticipant,
                "updatedAt" => $row["updatedAt"],

                "lastMessageInTheChat" => $messagesInfo["text"],
                "wasSentByMe" => $messagesInfo["wasSentByMe"] == 0 ? false : true,
                "unreadMessages" => $messagesInfo["unreadMessages"],
                "readStatus" => $messagesInfo["readStatus"] == 0 ? false : true,
                "isProposalMessage" => $messagesInfo["isProposalMessage"] == 0 ? false : true,

                "chatingWithName" => $nameAndProfile["userMutableName"],
                "chatingWithProfileUrl" => $nameAndProfile["userProfilePicURL"],
            ];
            //vamos enviar o outro participant, excluindo EU mesmo
            array_push($chats, $chat);
        }

        $stmt->close();
    }

    return $chats;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        require_once "Wamp64Connection.php";
        $conn = getMySQLConnection("conversations");

        $participant = $_GET['participant'];

        $chats = loadAllMyChats($conn, $participant);

        header('Content-Type: application/json');
        echo json_encode($chats);

        $conn->close();
    } catch (\Throwable $th) {
        echo "Ocorreu um erro: " . $th->getMessage();
    }
}

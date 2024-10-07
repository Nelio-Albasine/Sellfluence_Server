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

$userId = $_GET["userId"];

$queryMyConversations = "SELECT * FROM Chats WHERE receiverId = ?";
$stmt = $conn->prepare($queryMyConversations);
if ($stmt === false) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("s", $userId);
$stmt->execute();

/*
$createTBChat = "CREATE TABLE IF NOT EXISTS Chats(
        chatId VARCHAR(255) PRIMARY KEY,
        senderId VARCHAR(255),
        receiverId VARCHAR(255),
        conversation JSON
    )";
*/

$response = array(
    "chatingWith" => array(
        "receiverId" => null, //will return the\ userID of each chating with
        "receiverProfilePic" => null, //will return the Profile pic URL of each chating with
    ), //aqui ira retornar os ID em json de todos aqueles que estou conversando com eles
    "spoiler" => array(
        "textSpoiler" => null,
        "viewdStatus" => null,

    ), //aqui ira retornar a mensagem que ira ficar visivel como sendo a utlima mensagem no chat
);

$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Chat ID: "
         . $row['chatId'] 
        . " - SenderId: " 
        . $row['senderId']  
        . " - Conversation: " 
        . $row['conversation'] . "<br>";
    }
} else {
    echo "Nenhuma conversa encontrada para este usuário.";
}


$stmt->close();
$conn->close();
?>

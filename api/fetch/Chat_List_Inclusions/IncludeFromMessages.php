<?php
function getFromMessages($conn, $chatId): array
{
    $response = [
       "text" => null, 
       "wasSentByMe" => null, 
       "unreadMessages" => null, 
       "readStatus" => null, 
       "isProposalMessage" => null, 
    ];

    // Query para buscar a última mensagem e a contagem de mensagens não lidas
    $query = "
        SELECT text, isSent AS wasSentByMe, readStatus, isProposalMessage, 
               (SELECT COUNT(*) FROM Messages WHERE chatId = ? AND readStatus = 0) AS unreadMessages 
        FROM Messages 
        WHERE chatId = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ii", $chatId, $chatId); 
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response = $result->fetch_assoc();
        }
        $stmt->close();
    }

    return $response;
}

/*
$filePath = "../Wamp64Connection.php";
if (file_exists($filePath)) {
    require_once $filePath;

       $conn = getMySQLConnection("conversations");

    $messaesGot = getFromMessages($conn, "814395");
    echo json_encode($messaesGot);
} else {
    die("Erro: O arquivo $filePath não foi encontrado.");
}
    */



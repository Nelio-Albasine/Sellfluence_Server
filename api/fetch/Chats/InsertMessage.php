<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

ini_set('error_log', __DIR__ . '/error_inserting_message.log');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chatId = $_POST['chatId'];
    $messageId = $_POST['messageId'];
    $text = $_POST['text'];
    // $isSent = $_POST['isSent'];
    $isSent = false;

    // Aqui você pode validar os dados recebidos

    try {
        require_once "Wamp64Connection.php";

        $conn = getWamp64Connection("conversations");

        $sql = "INSERT INTO Messages (messageId, chatId, text, isSent, isEdited, readStatus, isProposalMessage)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $falseDefault = false;
        $stmt->bind_param("sisiiii", $messageId, $chatId, $text, $isSent, $falseDefault, $falseDefault, $falseDefault);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Mensagem inserida com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Falha ao inserir a mensagem.']);
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']);
}

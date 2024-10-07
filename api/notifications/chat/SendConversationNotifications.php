<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

//require '/home/parceria/sellfluence.com.br/vendor/autoload.php';
require './../../../vendor/autoload.php';
ini_set('error_log', __DIR__ . '/sendChatNotification.log');

use GuzzleHttp\Client;
use Google_Client;

try {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    if ($requestMethod == "POST") {
        $data = json_decode(file_get_contents('php://input'), true);

        if ($data === null) {
            logMessage("Erro: Dados JSON inválidos.");
            echo json_encode(["success" => false, "message" => "Dados JSON inválidos."]);
            exit;
        }

        $requiredFields = ['to', 'senderName', 'senderProfilePicURL', 'message', 'chatId'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            return empty($data[$field]);
        });

        if ($missingFields) {
            logMessage("Missing fields: " . implode(', ', $missingFields));
            echo json_encode(["success" => false, "message" => "Campos obrigatórios faltando: " . implode(', ', $missingFields)]);
            exit;
        }

        $to = $data["to"];
        $senderName = $data["senderName"];
        $senderProfilePicURL = $data["senderProfilePicURL"];
        $message = $data["message"];
        $chatId = $data["chatId"];
        $senderParticipantId = $data["senderParticipantId"];
        $timestamp = round(microtime(true) * 1000);

        $inclusionsData = includeFromMessages($chatId);
        $isProposalMessage = $inclusionsData['isProposalMessage'];
        $messageId = $inclusionsData['messageId'];
        $filóeUrl = $inclusionsData['fileUrl'];

        $result = sendChatNotification(
            $to,
            $senderName,
            $senderProfilePicURL,
            $message,
            $chatId,
            $timestamp,
            //include from messages
            $senderParticipantId,
            $isProposalMessage,
            $messageId,
            $fileUrl
        );

        logMessage("Notification sent result: $result");

        header('Content-Type: application/json; charset=utf-8');
        $response = [
            "success" => $result ? true : false,
            "message" => $result ? "Notificação de chat enviada com sucesso." : "Falha ao enviar a notificação de chat.",
            "result" => $result
        ];

        echo json_encode($response);
    }
} catch (\Throwable $th) {
    logMessage("An error occurred: " . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro ao processar a requisição."]);
} finally {
    if (isset($conn) && $conn !== null) {
        $conn->close();
    }
}

function logRequest($fileName)
{
    $logData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'body' => file_get_contents('php://input')
    ];
    $logContent = json_encode($logData, JSON_PRETTY_PRINT);
    logMessage("$fileName Requisição recebida:\n" . $logContent);
}


function logMessage($message)
{
    $logFile = __DIR__ . '/sendChatNotification.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}


function includeFromMessages($chatId): array
{
    require_once "../../../api/fetch/Wamp64Connection.php";

    $conn = getMySQLConnection("conversations");

    $response = [
        "isProposalMessage" => null,
        "messageId" => null,
        "fileUrl" => null
    ];

    $query = "
        SELECT isProposalMessage, messageId, fileUrl 
        FROM Messages 
        WHERE chatId = ?
    ";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Atualizando o array de resposta com os dados buscados
            $fetched = $result->fetch_assoc();
            $response = [
                "isProposalMessage" => $fetched['isProposalMessage'],
                "messageId" => $fetched['messageId'],
                "fileUrl" => $fetched['fileUrl']
            ];
        }
        $stmt->close();
    }

    if (isset($conn) && $conn !== null) {
        $conn->close();
    }

    return $response;
}


function sendChatNotification(
    $to,
    $senderName,
    $senderProfilePicURL,
    $message,
    $chatId,
    $timestamp,
    $senderParticipantId,
    $isProposalMessage,
    $messageId,
    $fileUrl,

) {
    $privateKeyFile = './../../../firebase_settings.json';
    if (!file_exists($privateKeyFile)) {
        logMessage('Arquivo privateKeyFile.json não encontrado.');
        die('Arquivo privateKeyFile.json não encontrado.');
    }

    // Carrega a chave privada do JSON
    $privateKeyData = json_decode(file_get_contents($privateKeyFile), true);
    if (!$privateKeyData) {
        logMessage('Erro ao decodificar o arquivo JSON.');
        die('Erro ao decodificar o arquivo JSON.');
    }

    // Autenticação com OAuth 2.0
    $client = new Google_Client();
    $client->setAuthConfig($privateKeyFile);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithAssertion();
    }
    $token = $client->getAccessToken();

    // Define o cabeçalho de autorização com o token OAuth 2.0
    $headers = [
        'Authorization' => 'Bearer ' . $token['access_token'],
        'Content-Type' => 'application/json',
    ];

    // URL do FCM
    $url = "https://fcm.googleapis.com/v1/projects/{$privateKeyData['project_id']}/messages:send";

    // Cria o payload da mensagem
    $messagePayload = [
        "message" => [
            "token" => $to,
            "data" => [
                "type" => "chat",
                "chatId" => (string) $chatId,
                "senderParticipantId" => (string) $senderParticipantId,
                "isProposalMessage" => (bool) $isProposalMessage,
                "chatingWithName" => (string) $senderName,
                "messageId" => (string) $messageId,
                "timestamp" => (string) $timestamp,
                "message" => (string) $message,
                "fileUrl" => (string) $fileUrl,
                "senderProfilePicURL" => (string) $senderProfilePicURL
            ]
        ]
    ];


    // Envia a mensagem
    $client = new Client(['verify' => true]); // Ativa a verificação SSL
    try {
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $messagePayload,
        ]);
        logMessage("Mensagem enviada com sucesso para o token: $to");
    } catch (\Exception $e) {
        logMessage("Erro ao enviar a mensagem: " . $e->getMessage());
        return false;
    }

    return $response->getBody()->getContents();
}

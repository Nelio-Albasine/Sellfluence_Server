<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '/home/parceria/sellfluence.com.br/vendor/autoload.php';
require 'SaveNotificationIntoDB.php';

use GuzzleHttp\Client;
use Google_Client;

function sendNotification(
    $to,
    $senderId,
    $amount,
    $notificationId,
    $timestamp,
    $receiverEmail,
    $receiverName,
    $companyName,
    $companyProfilePicUrl
) {
    $privateKeyFile = '../../../firebase_settings.json';

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
                "type" => "proposta",
                "senderId" => (string) $senderId,
                "amount" => (string) $amount,
                "notificationId" => (string) $notificationId,
                "timestamp" => (string) $timestamp,
                "companyName" => $companyName,
                "companyProfilePicUrl" => $companyProfilePicUrl
            ],
        ],
    ];

    // Envia a mensagem
    $client = new Client(['verify' => true]); // Desativa a verificação SSL para ambientes de teste
    $response = $client->post($url, [
        'headers' => $headers,
        'json' => $messagePayload,
    ]);

    require '/home/parceria/sellfluence.com.br/api/notifications/db_send_partnerships_emails.php';


    $valorFormatado = number_format($amount, 2, ',', '.');
    $formatedAmount = "R$ " . $valorFormatado;


    $locale = 'pt_BR';
    $dateType = IntlDateFormatter::LONG;
    $dateFormatter = new IntlDateFormatter($locale, $dateType, IntlDateFormatter::NONE);
    $currentDate = new DateTime();
    $formattedDate = $dateFormatter->format($currentDate);


    $emailSent = sendProposalEmailToReceiver($receiverName, $receiverEmail, $formatedAmount, $formattedDate, $notificationId);

    if ($emailSent) {
        // Retorna a resposta
        return $response->getBody()->getContents();
    } else {
        error_log("Ocorreu um erro ao enviar o email ao receiver");
        die("Ocorreu um erro ao enviar o email ao receiver");
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

function gerarIdAleatorio($value)
{
    $caracteres = '0123456789';
    $id = '';
    for ($i = 0; $i < $value; $i++) {
        $posicao = rand(0, strlen($caracteres) - 1);
        $id .= $caracteres[$posicao];
    }
    return $id;
}

try {

    $requestMethod = $_SERVER['REQUEST_METHOD'];
    if ($requestMethod == "POST") {
        $data = json_decode(file_get_contents('php://input'), true);
        logMessage("Received data: " . json_encode($data));

        $requiredFields = ['to', 'senderId', 'receiverId', 'amount', 'proposalItems'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            return empty($data[$field]);
        });

        if ($missingFields) {
            logMessage("Missing fields: " . implode(', ', $missingFields));
            echo json_encode(["success" => false, "message" => "Campos obrigatórios faltando: " . implode(', ', $missingFields)]);
            exit;
        }

        $to = $data["to"];
        $senderId = $data["senderId"];
        $receiverId = $data["receiverId"];
        $receiverEmail = $data["receiverEmail"];
        $receiverName = $data["receiverName"];
        $amount = $data["amount"];
        $companyName = $data["companyName"];
        $companyProfilePicUrl = $data["companyProfilePicUrl"];
        $notificationId = gerarIdAleatorio(8);
        $proposalItems = json_encode($data["proposalItems"]);
        $timestamp = round(microtime(true) * 1000);

        logMessage("Prepared data: to=$to, senderId=$senderId, receiverId=$receiverId, amount=$amount, notificationId=$notificationId, proposalItems=$proposalItems, timestamp=$timestamp");

        $notificationSavedInDB = saveNotificationIntoDB(
            $senderId,
            $receiverId,
            $amount,
            $notificationId,
            $proposalItems,
            $timestamp,
            $companyName,
            $companyProfilePicUrl
        );

        logMessage("Notification saved in DB: " . ($notificationSavedInDB ? "yes" : "no"));

        if ($notificationSavedInDB) {
            $result = sendNotification(
                $to,
                $senderId,
                $amount,
                $notificationId,
                $timestamp,
                $receiverEmail,
                $receiverName,
                $companyName,
                $companyProfilePicUrl
            );
            //send email to user
            logMessage("Notification sent result: $result");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "chatId" => $notificationId, "message" => "Notificação enviada com sucesso.", "result" => $result]);
        } else {
            logMessage("Failed to save notification in DB");
            echo json_encode(["success" => false, "message" => "Failed to save notification in DB."]);
        }
    }
} catch (\Throwable $th) {
    logMessage("An error occurred: " . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro ao processar a requisição."]);
} finally {
    if (isset($conn) && $conn !== null) {
        $conn->close();
    }
}

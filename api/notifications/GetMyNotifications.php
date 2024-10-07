<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function logMessage($message)
{
    error_log($message);
}

if (file_exists('/home/parceria/sellfluence.com.br/connection.php')) {
    require('/home/parceria/sellfluence.com.br/connection.php');
} else {
    logMessage('Arquivo connection.php não encontrado.');
    throw new Exception('Arquivo connection.php não encontrado.');
}

function jsonResponse($data)
{
    echo json_encode($data);
    exit;
}

function fetchNotifications($conn, $receiver_id)
{
    $allNotifications = [];
    // SQL query to get all notifications
    $sql = "SELECT senderId, notifications FROM Notifications";

    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $notifications = json_decode($row['notifications'], true);

            if (isset($notifications['allNotifications'][$receiver_id])) {
                foreach ($notifications['allNotifications'][$receiver_id] as $notificationId => $notification) {
                    $allNotifications[$receiver_id][$notificationId] = [
                        "timestamp" => $notification['timestamp'],
                        "status" => $notification['status'],
                        "amount" => $notification['amount'],
                        "proposalItems" => $notification['proposalItems'],
                        "companyName" => $notification['companyName'] ?? null,
                        "companyProfilePicUrl" => $notification['companyProfilePicUrl'] ?? null
                    ];
                }
            }
        }
        $result->free();
    } else {
        logMessage("Erro ao executar a consulta: " . $conn->error);
    }

    return ["allNotifications" => $allNotifications];
}

$response = [
    "result" => null,
    "message" => null
];

function filter_string(string $string): string
{
    $str = preg_replace('/\x00|<[^>]*>?/', '', $string);
    return str_replace(["'", '"'], ['&#39;', '&#34;'], $str);
}

try {
    $conn = getDatabaseConnection();
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    if ($requestMethod == "GET") {
        $response["error"] = null;

        if (isset($_GET["id"])) {
            $receiver_id = filter_string($_GET["id"]);
            logMessage("id sanitizado: " . $receiver_id);

            if ($receiver_id) {
                $result = fetchNotifications($conn, $receiver_id);
                $response["result"] = $result;
                $response["message"] = "Notificações obtidas com sucesso.";
            } else {
                $response["message"] = "Parameter 'id' is missing or invalid.";
            }
        } else {
            $response["message"] = "Parameter 'id' is missing.";
        }
    } else {
        $response["message"] = "Invalid request method.";
    }
    
    error_log("message getMyNotys " .print_r($response, true));
    jsonResponse($response);
} catch (Exception $e) {
    logMessage("Erro: " . $e->getMessage());
    $response["message"] = $e->getMessage();
    jsonResponse($response);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>

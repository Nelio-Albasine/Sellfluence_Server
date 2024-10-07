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

try {
    function getMyNotificationsCount($connection, $userId)
    {
        $notificationCount = 0;
        $stmt = $connection->prepare("SELECT COUNT(*) AS notificationCount FROM Notifications WHERE status = 0 AND receiverId = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $connection->error);
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed: " . $stmt->error);
        }

        $stmt->bind_result($notificationCount);
        $stmt->fetch();
        $stmt->close();

        return $notificationCount;
    }

    function filter_string(string $string): string
    {
        $str = preg_replace('/\x00|<[^>]*>?/', '', $string);
        return str_replace(["'", '"'], ['&#39;', '&#34;'], $str);
    }

    if (isset($_GET[filter_string("userId")])) {
        $conn = getDatabaseConnection();

        $userId = intval($_GET[filter_string("userId")]);

        $notificationCount = getMyNotificationsCount($conn, $userId);

        echo json_encode(['notificationCount' => $notificationCount]);
    } else {
        logMessage("The GET parameter 'userId' is required!");
        echo json_encode(['error' => "The GET parameter 'userId' is required!"]);
    }
} catch (Throwable $th) {
    logMessage("Ocorreu um erro no GetMyNotificationsCount erro: " . $th->getMessage());
    echo json_encode(['error' => "Ocorreu um erro: " . $th->getMessage()]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>

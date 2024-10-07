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
    die('Arquivo connection.php não encontrado.');
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod == "POST") {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $requiredFields = ['notificationId', 'receiverId', 'isTo'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            return empty($data[$field]);
        });

        if ($missingFields) {
            logMessage("Missing fields: " . implode(', ', $missingFields));
            echo json_encode(["success" => false, "message" => "Campos obrigatórios faltando: " . implode(', ', $missingFields)]);
            exit;
        }

        $notificationId = $data["notificationId"];
        $receiverId = $data["receiverId"];
        $isTo = intval($data["isTo"]);

        $newStatus;

        $conn = getDatabaseConnection();

        switch ($isTo) {
            case 1:
                # change status to: 1 => the noty was viewed
                $newStatus = 1;
                break;
            case 2:
                # change status to: 2 => the noty will be as Archived
                $newStatus = 2;
                break;
            case 3:
                # change status to: 3 => the noty will be as DELETED
                $newStatus = 3;
                break;
            case 4:
                # Delete permanently the notification
                # this action is only trigged by the sender
                $queryToDelete = "UPDATE Notifications 
                                  SET notifications = JSON_REMOVE(notifications, CONCAT('$.allNotifications.', ?, '.', ?))
                                  WHERE JSON_CONTAINS_PATH(notifications, 'one', CONCAT('$.allNotifications.', ?, '.', ?))";
                
                $stmt = $conn->prepare($queryToDelete);
                $stmt->bind_param('sss', $receiverId, $notificationId, $receiverId, $notificationId);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Notificação deletada permanentemente com sucesso.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Nenhuma notificação encontrada com o ID especificado.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao deletar a notificação: ' . $stmt->error]);
                }

                $stmt->close();
                return;
        }

        $queryToUpdate = "UPDATE Notifications 
                          SET notifications = JSON_SET(notifications, CONCAT('$.allNotifications.', ?, '.', ?, '.status'), ?) 
                          WHERE JSON_CONTAINS_PATH(notifications, 'one', CONCAT('$.allNotifications.', ?, '.', ?))";

        $stmt = $conn->prepare($queryToUpdate);
        $stmt->bind_param('ssiss', $receiverId, $notificationId, $newStatus, $receiverId, $notificationId);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Status da notificação atualizado com sucesso.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nenhuma notificação encontrada com o ID especificado.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status da notificação: ' . $stmt->error]);
        }

        $stmt->close();
    } catch (\Throwable $th) {
        error_log("Ocorreu um erro no arquivo update or delete notifications, o erro: " . $th->getMessage());
        echo json_encode(['error' => 'Ocorreu um erro: ' . $th->getMessage()]);
    }
} else {
    error_log("Invalid request method!");
    echo json_encode(['error' => 'Método de requisição inválido!']);
}

$conn->close();
?>

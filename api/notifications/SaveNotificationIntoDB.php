<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function logMessage($message)
{
    error_log($message);
}


if (file_exists('/home/parceria/sellfluence.com.br/connection.php')) {
    require ('/home/parceria/sellfluence.com.br/connection.php');
} else {
    logMessage('Arquivo connection.php não encontrado.');
    die('Arquivo connection.php não encontrado.');
}


try {
    function saveNotificationIntoDB(
        $senderId, 
        $receiverId, 
        $amount, 
        $notificationId, 
        $proposalItems, 
        $timestamp,
        $companyName,
        $companyProfilePicUrl)
    {
        function createTableIfNotExists($conn)
        {
            $createTBstmt = "CREATE TABLE IF NOT EXISTS Notifications(
                id INT PRIMARY KEY AUTO_INCREMENT,
                senderId VARCHAR(255) UNIQUE,
                notifications JSON NOT NULL
            )";
            if ($conn->query($createTBstmt) !== TRUE) {
                logMessage("Erro ao criar a tabela Notifications: " . $conn->error);
                die("Erro ao criar a tabela Notifications: " . $conn->error);
            }
        }

        $conn = getDatabaseConnection();
        createTableIfNotExists($conn);


        //verify is this receiver id already exists in the list of the senderId notificatios
        function checkIfSenderExistsOrContainsThisReceiver($conn, $senderId, $receiverId)
        {
            // Query to check if the sender exists
            $checkIfSenderExists = "SELECT notifications FROM Notifications WHERE senderId = ?";
            $stmt = $conn->prepare($checkIfSenderExists);
            $stmt->bind_param("s", $senderId);
            $stmt->execute();
            $stmt->store_result();  // Store the result set
            $rowExists = $stmt->num_rows > 0;
            $valueToReturn = 0;
            if ($rowExists) {
                $notificationsJson = "empty";
                $stmt->bind_result($notificationsJson);
                $stmt->fetch();
                $notifications = json_decode($notificationsJson, true);
                //error_log("notificationsDecoded are:" . print_r($notifications, true));

                if (isset($notifications["allNotifications"][$receiverId])) {
                    $valueToReturn = 0;
                } else {
                    $valueToReturn = 1;
                }
            } else {
                $valueToReturn = 2;
            }
            $stmt->close();
            return $valueToReturn;
        }


        #TAG 0
        function updateReceiverNotificationsInSenderNotificationsList($constructors)
        {
            $conn = $constructors['conn'];
            $senderId = $constructors['senderId'];
            $receiverId = $constructors['receiverId'];
            $proposalItems = $constructors['proposalItems'];
            $notificationId = $constructors['notificationId'];
            $timestamp = $constructors['timestamp'];
            $amount = $constructors['amount'];
            $companyName = $constructors['companyName'];
            $companyProfilePicUrl = $constructors['companyProfilePicUrl'];

            $queryAvailableNotifications = "
                SELECT notifications 
                FROM Notifications 
                WHERE JSON_CONTAINS_PATH(notifications, 'one', CONCAT('$.allNotifications.', ?)) 
                  AND senderId = ?;
            ";

            $stmtAvailableNotifications = $conn->prepare($queryAvailableNotifications);
            if (!$stmtAvailableNotifications) {
                error_log("Erro ao preparar o stmt: " . $conn->error);
                die("Erro ao preparar o stmt: " . $conn->error);
            } else {
                $stmtAvailableNotifications->bind_param("ss", $receiverId, $senderId);
                if ($stmtAvailableNotifications->execute()) {
                    $stmtAvailableNotifications->store_result();
                    if ($stmtAvailableNotifications->num_rows > 0) {
                        $receiver = "";
                        $stmtAvailableNotifications->bind_result($receiver);
                        $stmtAvailableNotifications->fetch();
                        // Extrair a lista de notificações existente
                        $notifications = json_decode($receiver, true);
                        error_log("result do notifications: " . print_r($notifications, true));
                        // Adicionar ou atualizar a nova notificação à lista
                        $notifications['allNotifications'][$receiverId][$notificationId] = [
                            "timestamp" => $timestamp,
                            "status" => 0,
                            "amount" => $amount,
                            "proposalItems" => $proposalItems,
                            "companyName" => $companyName,
                            "companyProfilePicUrl" => $companyProfilePicUrl
                        ];
                        error_log("result do notifications apos adicionar a nova: " . print_r($notifications, true));
                        // Atualizar o JSON no banco de dados
                        $updatedNotificationsJson = json_encode($notifications);
                        $updateNotifications = "
                            UPDATE Notifications 
                            SET notifications = ? 
                            WHERE senderId = ?
                        ";
                        $updateStmt = $conn->prepare($updateNotifications);
                        if (!$updateStmt) {
                            error_log("Erro ao preparar o updateStmt: " . $conn->error);
                            die("Erro ao preparar o updateStmt: " . $conn->error);
                        } else {
                            $updateStmt->bind_param("ss", $updatedNotificationsJson, $senderId);
                            if ($updateStmt->execute()) {
                                return true; // Notificações atualizadas com sucesso
                            } else {
                                error_log("Erro ao executar o updateStmt: " . $conn->error);
                                die("Erro ao executar o updateStmt: " . $conn->error);
                            }
                        }
                    }
                } else {
                    error_log("Erro ao executar o stmt: " . $conn->error);
                    die("Erro ao executar o stmt: " . $conn->error);
                }
            }
        }


        #TAG 1
        function updateSenderIdNotificationsListWithNewReceiver($constructors)
        {
            //the receiver do not exists in the sender receiver's list
            $conn = $constructors['conn'];
            $senderId = $constructors['senderId'];
            $receiverId = $constructors['receiverId'];
            $proposalItems = $constructors['proposalItems'];
            $notificationId = $constructors['notificationId'];
            $timestamp = $constructors['timestamp'];
            $amount = $constructors['amount'];
            $companyName = $constructors['companyName'];
            $companyProfilePicUrl = $constructors['companyProfilePicUrl'];

            $queryAvailableNotifications = "SELECT notifications FROM Notifications  WHERE senderId = ?";

            $stmtAvailableNotifications = $conn->prepare($queryAvailableNotifications);
            if (!$stmtAvailableNotifications) {
                error_log("Erro ao preparar o stmt: " . $conn->error);
                die("Erro ao preparar o stmt: " . $conn->error);
            } else {
                $stmtAvailableNotifications->bind_param("s", $senderId);
                if ($stmtAvailableNotifications->execute()) {
                    $stmtAvailableNotifications->store_result();
                    $receiver = "";
                    $stmtAvailableNotifications->bind_result($receiver);
                    $stmtAvailableNotifications->fetch();
                    // Extrair a lista de notificações existente
                    $notifications = json_decode($receiver, true);
                    error_log("result do notifications: " . print_r($notifications, true));
                    // Adicionar ou atualizar a nova notificação à lista
                    $notifications['allNotifications'][$receiverId][$notificationId] = [
                        "timestamp" => $timestamp,
                        "status" => 0,
                        "amount" => $amount,
                        "proposalItems" => $proposalItems,
                        "companyName" => $companyName,
                        "companyProfilePicUrl" => $companyProfilePicUrl
                    ];
                    error_log("result do notifications apos adicionar a nova: " . print_r($notifications, true));
                    // Atualizar o JSON no banco de dados
                    $updatedNotificationsJson = json_encode($notifications);
                    $updateNotifications = "
                        UPDATE Notifications 
                        SET notifications = ? 
                        WHERE senderId = ?
                    ";
                    $updateStmt = $conn->prepare($updateNotifications);
                    if (!$updateStmt) {
                        error_log("Erro ao preparar o updateStmt: " . $conn->error);
                        die("Erro ao preparar o updateStmt: " . $conn->error);
                    } else {
                        $updateStmt->bind_param("ss", $updatedNotificationsJson, $senderId);
                        if ($updateStmt->execute()) {
                            return true; // Notificações atualizadas com sucesso
                        } else {
                            error_log("Erro ao executar o updateStmt: " . $conn->error);
                            die("Erro ao executar o updateStmt: " . $conn->error);
                        }
                    }
                } else {
                    error_log("Erro ao executar o stmt: " . $conn->error);
                    die("Erro ao executar o stmt: " . $conn->error);
                }
            }


        }


        #TAG 2
        function insertThisSenderIntoNotificationsTable(
            $constructors
        ) {
            //the sender do not exists in the notications table
            $conn = $constructors['conn'];
            $senderId = $constructors['senderId'];
            $receiverId = $constructors['receiverId'];
            $proposalItems = $constructors['proposalItems'];
            $notificationId = $constructors['notificationId'];
            $timestamp = $constructors['timestamp'];
            $amount = $constructors['amount'];
            $companyName = $constructors['companyName'];
            $companyProfilePicUrl = $constructors['companyProfilePicUrl'];

            $arrayNewNotification = array(
                "allNotifications" => array(
                    $receiverId => array(
                        $notificationId => array(
                            "timestamp" => $timestamp,
                            "status" => 0,
                            "amount" => $amount,
                            "proposalItems" => $proposalItems,
                            "companyName" => $companyName,
                            "companyProfilePicUrl" => $companyProfilePicUrl
                        )
                    )
                )
            );

            $newNotificationJson = json_encode($arrayNewNotification);
            $insertStmt = "INSERT INTO Notifications (senderId, notifications) VALUES (?,?)";

            $stmt = $conn->prepare($insertStmt);
            if (!$stmt) {
                logMessage("Erro ao preparar o stmt: " . $conn->error);
                die("Erro ao preparar o stmt: " . $conn->error);
            } else {
                $stmt->bind_param("ss", $senderId, $newNotificationJson);
                if ($stmt->execute()) {
                    return true;
                } else {
                    logMessage("Erro ao executar o stmt: " . $conn->error);
                    die("Erro ao executar o stmt: " . $conn->error);
                }
            }
        }


        $arrayFunctionConstructors = array(
            'conn' => $conn,
            'senderId' => $senderId,
            'receiverId' => $receiverId,
            'proposalItems' => $proposalItems,
            'notificationId' => $notificationId,
            'timestamp' => $timestamp,
            'amount' => $amount,
            'companyName' => $companyName,
            'companyProfilePicUrl' => $companyProfilePicUrl
        );
        $result = null;

        $checkToProssegue = checkIfSenderExistsOrContainsThisReceiver($conn, $senderId, $receiverId);
        error_log("result do checkToProssegue: " . $checkToProssegue);

        switch ($checkToProssegue) {
            case 0:
                # ReceiverId exists in the sender's notifications list
                #lets update the ReceiverId notifications list
                $result = updateReceiverNotificationsInSenderNotificationsList(
                    $arrayFunctionConstructors
                );
                break;
            case 1:
                # ReceiverId does not exist in the sender's notifications list
                #lets create new notification in the ReceiverId notifications list with this new receiver
                $result = updateSenderIdNotificationsListWithNewReceiver(
                    $arrayFunctionConstructors
                );
                break;
            case 2:
                # No matching senderId found
                #is the first time this sender sending notificatin
                $result = insertThisSenderIntoNotificationsTable(
                    $arrayFunctionConstructors
                );
                break;
        }

        return $result;
    }
} catch (\Throwable $th) {
    error_log("Ocorreu um erro ao salvar a notificacao: " . $th->getMessage());
    json_encode("ocorreu um erro no catch: " . $th->getMessage());
}

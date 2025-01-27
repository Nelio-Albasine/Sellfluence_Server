<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/error_getOrCreateUserInfo.log');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data["userId"]) || empty(trim($data["userInfo"]))) {
            http_response_code(400);
            echo json_encode(["error" => "Parâmetro 'userId' ou 'userInfo' ausente ou vazio."]);
            error_log("Parâmetro 'userId' ou 'userInfo' ausente ou vazio.");
            exit;
        }

        $userId = trim($data["userId"]);
        $userInfo = $data["userInfo"];

        require_once "../Wamp64Connection.php";
        $conn = getWamp64Connection("users");

        if (!$conn) {
            http_response_code(500);
            echo json_encode(["error" => "Falha na conexão com o banco de dados."]);
            error_log("Falha na conexão com o banco de dados.");
            exit;
        }

        $outPut = getOrCreateUser($conn, $userId, $userInfo);

        if ($outPut === null) {
            http_response_code(500);
            echo json_encode(["error" => "Erro ao verificar se o usuário está registrado."]);
            error_log("Erro ao verificar se o usuário está registrado.");
            exit;
        }

        error_log(print_r($outPut, true));

        echo json_encode($outPut);
    } catch (\Throwable $th) {
        error_log("Erro no bloco try-catch: " . $th->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Ocorreu um erro inesperado."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "O método da requisição não é GET."]);
    error_log("Método de requisição não é GET.");
}

function getOrCreateUser($conn, $userId, $userInfo)
{
    try {
        $query = "SELECT userEmail FROM Usuarios WHERE userId = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            error_log("Falha ao preparar a query: " . $conn->error);
            return null;
        }

        $stmt->bind_param("s", $userId);

        if (!$stmt->execute()) {
            error_log("Falha na execução da query: " . $stmt->error);
            return null;
        }

        $result = $stmt->get_result();

        if (!$result) {
            error_log("Erro ao obter resultado da query: " . $stmt->error);
            return null;
        }

        if ($result->num_rows > 0) {
            //User already exists
            $userData = $result->fetch_assoc();
            $userEmail = $userData["userEmail"];

            $stmt->close();
            $query = "SELECT * FROM UserProfile WHERE userId = ?";
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                error_log("Falha ao preparar a query: " . $conn->error);
                return null;
            }

            $stmt->bind_param("s", $userId);

            if (!$stmt->execute()) {
                error_log("Falha na execução da query: " . $stmt->error);
                return null;
            }

            $result = $stmt->get_result();

            if (!$result) {
                error_log("Erro ao obter resultado da query: " . $stmt->error);
                return null;
            }

            $userAllInfo = $result->fetch_assoc();
            $userAllInfo["userEmail"] = $userEmail;

            return [
                "success" => true,
                "data" => $userAllInfo,
                "isNewUser" => false
            ];
        } else {
            //User does not exist, lets create him
            $insertionResponse =  insertNewUser($conn, $userInfo);

            return [
                "success" => $insertionResponse,
                "isNewUser" => true,
                "data" => null
            ];
        }
    } catch (\Throwable $th) {
        error_log("Erro na função checkIfUserIsRegistered: " . $th->getMessage());
        return null;
    } finally {
        // Garantir que o statement seja fechado se existir
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }
}


function insertNewUser($conn, $userInfo)
{
    $userId = $userInfo['userId'];
    $userEmail = $userInfo['userEmail'];
    $userEmailName = $userInfo['userEmailName'];
    $notificationToken = $userInfo['notificationToken'];

    // Primeira query - Inserir usuário na tabela Usuarios
    $insertNewUser = "
        INSERT INTO Usuarios (userId, userEmailName, userEmail, notificationToken)
        VALUES (?, ?, ?, ?)
    ";

    $stmtInsertUser = $conn->prepare($insertNewUser);
    $stmtInsertUser->bind_param("ssss", $userId, $userEmailName, $userEmail, $notificationToken);

    // Verifica se a primeira query foi executada com sucesso
    if (!$stmtInsertUser->execute()) {
        $stmtInsertUser->close();
        $conn->close();
        return false;
    }

    // Segunda query - Inserir informações no UserProfile
    $insertUserProfileInfo = "
        INSERT INTO UserProfile (
            userId, graphInfluencerId, userProfilePicURL, userMutableName, 
            userAccountType, userBiography, userLocation, userTags, userPaymentMethods
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $userLocation = json_encode([
        "isSetuped" => false,
        "userCountry" => null,
        "userState" => null,
        "userCity" => null
    ], JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $userTags = json_encode([
        "isSetuped" => false,
        "firstTag" => null,
        "secondTag" => null,
        "thirdTag" => null
    ], JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $userPaymentMethods = json_encode([
        "isSetuped" => false,
        "pixGateway" => null,
        "visaGateway" => null
    ], JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $userAccountType = json_encode([
        "isSetuped" => false,
        "Influencer" => [
            "isInfluencer" => null,
            "graphInfluencerId" => null,
            "setupedAt" => null,
            "followers" => 0
        ],
        "Company" => [
            "isCompany" => null,
            "companyId" => null,
            "setupedAt" => null,
            "companyName" => null
        ]
    ], JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $stmtInsertUserProfileInfo = $conn->prepare($insertUserProfileInfo);

    // Definir esses bind param como nuláveis
    $stmtInsertUserProfileInfo->bind_param(
        "sssssssss",
        $userId,
        $param1 = null,
        $param2 = null,
        $param3 = null,
        $userAccountType,
        $param4 = null,
        $userLocation,
        $userTags,
        $userPaymentMethods
    );

    // Verifica se a segunda query foi executada com sucesso
    if (!$stmtInsertUserProfileInfo->execute()) {
        $stmtInsertUserProfileInfo->close();
        $stmtInsertUser->close();
        $conn->close();
        return false;
    }

   
    $stmtInsertUserProfileInfo->close();
    $stmtInsertUser->close();
    $conn->close();

    return true;
}

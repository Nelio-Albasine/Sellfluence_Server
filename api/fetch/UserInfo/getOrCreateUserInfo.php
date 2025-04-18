<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/error_getOrCreateUserInfo.log');
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/error_getOrCreateUserInfo.log';

function logRequest($logFile)
{
    $requestDetails = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'headers' => getallheaders(),
        'query_params' => $_GET,
        'body' => file_get_contents('php://input')
    ];

    $logEntry = json_encode($requestDetails, JSON_PRETTY_PRINT);
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
}
logRequest($logFile);

// Função para verificar e criar colunas ausentes
function ensureColumnExists($conn, $table, $column, $definition) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        error_log("Coluna '$column' não existe na tabela '$table'. Criando...");
        $alterQuery = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        return $conn->query($alterQuery);
    }
    
    return true;
}

// Função para garantir que todas as colunas necessárias existam
function ensureRequiredColumns($conn) {
    // Agora apenas temos a tabela UserProfile
    ensureColumnExists($conn, 'UserProfile', 'userId', 'VARCHAR(191) NOT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userEmailName', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userEmail', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'notificationToken', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'graphInfluencerId', 'VARCHAR(20) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userProfilePicURL', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userMutableName', 'VARCHAR(50) DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userBiography', 'TEXT DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userAccountType', 'JSON DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userLocation', 'JSON DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userTags', 'JSON DEFAULT NULL');
    ensureColumnExists($conn, 'UserProfile', 'userPaymentMethods', 'JSON DEFAULT NULL');
}

// Function to extract relevant user info from Kotlin object string
function parseUserInfoFromString($str) {
    $userInfo = [];
    
    // Extract userId
    if (preg_match('/userId=([^,\)]+)/', $str, $matches)) {
        $userInfo['userId'] = trim($matches[1]);
    }
    
    // Extract notificationToken
    if (preg_match('/notificationToken=([^,\)]+)/', $str, $matches)) {
        $value = trim($matches[1]);
        $userInfo['notificationToken'] = ($value === 'null') ? null : $value;
    }
    
    // Extract userEmailName
    if (preg_match('/userEmailName=([^,\)]+)/', $str, $matches)) {
        $userInfo['userEmailName'] = trim($matches[1]);
    }
    
    // Extract userEmail
    if (preg_match('/userEmail=([^,\)]+)/', $str, $matches)) {
        $userInfo['userEmail'] = trim($matches[1]);
    }
    
    return $userInfo;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // First try to read from the request body
        $body = file_get_contents('php://input');
        $data = [];
        
        if (!empty($body)) {
            $data = json_decode($body, true);
        }
        
        // If not found in body, try URL parameters
        if (empty($data) || !isset($data["userId"])) {
            if (isset($_GET["userId"])) {
                $data["userId"] = $_GET["userId"];
            }
            
            if (isset($_GET["userInfo"])) {
                // Parse the essential fields from the Kotlin object string
                $userInfoStr = $_GET["userInfo"];
                $data["userInfo"] = parseUserInfoFromString($userInfoStr);
            }
        }

        if (!isset($data["userId"]) || !isset($data["userInfo"]) || empty($data["userInfo"])) {
            http_response_code(400);
            echo json_encode(["error" => "Parâmetro 'userId' ou 'userInfo' ausente ou vazio."]);
            error_log("Parâmetro 'userId' ou 'userInfo' ausente ou vazio.");
            exit;
        }

        $userId = trim($data["userId"]);
        $userInfo = $data["userInfo"];

        require_once "../../conn/Wamp64Connection.php";
        $conn = getWamp64Connection("users");

        if (!$conn) {
            http_response_code(500);
            echo json_encode(["error" => "Falha na conexão com o banco de dados."]);
            error_log("Falha na conexão com o banco de dados.");
            exit;
        }
        
        // Verificar e criar colunas ausentes
        ensureRequiredColumns($conn);

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
    echo json_encode(["error" => "O método da requisição não é POST."]);
    error_log("Método de requisição não é POST.");
}

function getOrCreateUser($conn, $userId, $userInfo)
{
    try {
        // Consulta único para verificar se o usuário já existe
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

        if ($result->num_rows > 0) {
            //User already exists
            $userAllInfo = $result->fetch_assoc();
            
            // Decodificar os campos JSON
            if (isset($userAllInfo['userAccountType'])) {
                $userAllInfo['userAccountType'] = json_decode($userAllInfo['userAccountType'], true);
            }
            if (isset($userAllInfo['userLocation'])) {
                $userAllInfo['userLocation'] = json_decode($userAllInfo['userLocation'], true);
            }
            if (isset($userAllInfo['userTags'])) {
                $userAllInfo['userTags'] = json_decode($userAllInfo['userTags'], true);
            }
            if (isset($userAllInfo['userPaymentMethods'])) {
                $userAllInfo['userPaymentMethods'] = json_decode($userAllInfo['userPaymentMethods'], true);
            }
            
            return [
                "success" => true,
                "isNewUser" => false,
                "userInfo" => $userAllInfo  
            ];
        } else {
            //User does not exist, lets create him
            $insertionResponse = insertNewUser($conn, $userInfo);

            return [
                "success" => $insertionResponse,
                "isNewUser" => true,
                "userInfo" => null
            ];
        }
    } catch (\Throwable $th) {
        error_log("Erro na função getOrCreateUser: " . $th->getMessage());
        return null;
    } finally {
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

    // Inserir diretamente no UserProfile
    $insertUserProfileInfo = "
        INSERT INTO UserProfile (
            userId, userEmailName, userEmail, notificationToken, graphInfluencerId, 
            userProfilePicURL, userMutableName, userAccountType, userBiography, 
            userLocation, userTags, userPaymentMethods
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

    $stmt = $conn->prepare($insertUserProfileInfo);

    if (!$stmt) {
        error_log("Falha ao preparar a query de inserção: " . $conn->error);
        $conn->close();
        return false;
    }

    // Definir variáveis separadamente para parâmetros nulos
    $param1 = null;
    $param2 = null; 
    $param3 = null;
    $param4 = null;

    // Bind parameters corretamente
    $stmt->bind_param(
        "ssssssssssss",
        $userId,
        $userEmailName,
        $userEmail,
        $notificationToken,
        $param1,
        $param2,
        $param3,
        $userAccountType,
        $param4,
        $userLocation,
        $userTags,
        $userPaymentMethods
    );

    if (!$stmt->execute()) {
        error_log("Falha na execução da query de inserção: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return false;
    }

    $stmt->close();
    $conn->close();

    return true;
}
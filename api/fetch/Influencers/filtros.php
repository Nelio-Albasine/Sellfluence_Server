<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/home/error_HomeFiltersInfluencers.log');
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../../logs/home/error_HomeFiltersInfluencers.log';

/**
 * @Prioridades:
 * 1. Estado -> Cidade
 * 2. Conteúdo (Reel, Stories, etc.)
 * 3. Preço
 * 4. Idade (Do influenciador)
 * 5. Seguidores
 *
 * @Regras de Filtragem:
 * 1. Obter os influencers com base no Estado e Cidade.
 * 2. Desses influenciadores obtidos, filtrar os que se adequam ao preço definido pelo usuário.
 * 3. Caso o usuário não tenha definido um preço mínimo e máximo, retornar todos os influenciadores do Estado e Cidade selecionados.
 * 4. Para os influenciadores resultantes, obter a contagem de seguidores e aplicar as seguintes regras:
 *    4.1. Se o usuário tiver definido um intervalo de preço, filtrar também pelo número de seguidores:
 *         - Primeiro, obter todos os influenciadores.
 *         - Em seguida, dos influenciadores já filtrados, aplicar o filtro de seguidores no banco de dados.
 *    4.2. Se o usuário não tiver definido um intervalo de preço, retornar os influenciadores sem filtro adicional de seguidores.
 */



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

// Verifica se o método da requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Verifica se o Host é permitido
$allowedHosts = ['10.0.2.2'];
$requestHost = $_SERVER['HTTP_HOST'];

if (!in_array($requestHost, $allowedHosts)) {
    http_response_code(403); // Proibido
    echo json_encode(['error' => 'Host not allowed']);
    exit;
}

// Decodifica o corpo da requisição
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se o JSON foi decodificado corretamente
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extrai as chaves do corpo da requisição e atribui a variáveis
$selectedAges = $data['selectedAges'] ?? [];

$selectedCities = $data['selectedCities'] ?? [];
$selectedContentTypes = $data['selectedContentTypes'] ?? [];
$selectedFollowers = $data['selectedFollowers'] ?? ['max' => 0, 'min' => 0];
$selectedNiches = $data['selectedNiches'] ?? [];
$selectedPrices = $data['selectedPrices'] ?? ['max' => 0.0, 'min' => 0.0];
$selectedStates = $data['selectedStates'] ?? [];
$cursor = isset($data['cursor']) && $data['cursor'] !== 'null' ? $data['cursor'] : null;

$limit = 10;

require_once "../../conn/Wamp64Connection.php";

$conn = getWamp64Connection("userAccType");

if (!$conn) {
    die("Erro: Falha na conexão com o banco de dados.");
}


try {
    $response = getAllInfluencersInstaGramuserNamesAndIdsByQuery($search, $cursor, $limit);

    if (empty($response)) {
        error_log("Influencers home:" . print_r($finalResponse, true));

        echo json_encode($finalResponse);
        if ($conn) {
            $conn->close();
        }
        exit;
    }

    $graphInfluencerUserNames = $response["graphInfluencerUserName"];
    $graphInfluencerIds = $response["graphInfluencerId"];
    $hasMoreItems = $response["hasMoreItems"];

    $additionalInfo = getInfluencersInfoBatch($graphInfluencerIds);

    $parsedData = [];
    $curlMulti = [];
    $mh = curl_multi_init();

    foreach ($graphInfluencerUserNames as $index => $influencerAtual) {
        $endpoint = "https://graph.facebook.com/v18.0/$accountId?fields=business_discovery.username($influencerAtual){username,website,name,ig_id,id,profile_picture_url,biography,follows_count,followers_count}&access_token=$token";
        // $endpoint = "https://graph.facebook.com/v18.0/17841460733194402?fields=business_discovery.username(ian.lamar_){username,website,name,ig_id,id,profile_picture_url,biography,follows_count,followers_count,media_count,media{caption,like_count,comments_count,media_url,permalink,media_type}}&access_token=$token";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true); // Inclui cabeçalhos na resposta para verificar status HTTP
        $curlMulti[$index] = $ch;
        curl_multi_add_handle($mh, $ch);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);

    foreach ($curlMulti as $index => $ch) {
        $rawResponse = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);

        // Separa cabeçalhos e corpo da resposta
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        // Verifica código de status HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            error_log("Erro na requisição cURL para o influenciador {$graphInfluencerUserNames[$index]}: HTTP $httpCode");
            error_log("Cabeçalhos: $headers");
            error_log("Corpo: $body");

            if ($httpCode === 401) {
                error_log("Token expirado ou inválido.");
            } elseif ($httpCode === 403) {
                error_log("Acesso negado para o endpoint.");
            } elseif ($httpCode === 400) {
                error_log("Requisição inválida.");
            }

            continue; // Pula para o próximo influenciador
        }

        $influencerId = $graphInfluencerIds[$index];
        $businessDiscoveryJson = json_decode($body, true)['business_discovery'] ?? null;
        error_log("businessDiscoveryJson:" . print_r($businessDiscoveryJson, true));

        if ($businessDiscoveryJson) {
            $info = $additionalInfo[$influencerId] ?? [
                'location' => null,
                'tags' => '',
                'biography' => null,
                'averageRating' => 0,
            ];

            $parsedData[] = parseJsonToDataAllInfluencers($businessDiscoveryJson, $info);
        }
    }


    curl_multi_close($mh);

    // Define o próximo cursor com base na presença de mais itens
    $nextCursor = null;
    if (!empty($response)) {
        $nextCursor = $hasMoreItems ? end($graphInfluencerIds) : null;
    }

    $finalResponse = [
        'data' => $parsedData,
        'nextCursor' => $nextCursor,
    ];

    error_log("Influencers home:" . print_r($finalResponse, true));

    echo json_encode($finalResponse);
} catch (\Throwable $th) {
    error_log("Ocorreu um erro: " . $th->getMessage());
}



function parseJsonToDataAllInfluencers($data, $info)
{
    return [
        'influencerName' => $data['name'] ?? '',
        'influencerDescription' => $data['biography'] ?? '',
        'influencerUserName' => $data['username'] ?? '',
        'influencerProfileURL' => $data['profile_picture_url'] ?? null,
        'influencerFollowers' => $data['followers_count'] ?? 0,
        'influencerFollowing' => $data['follows_count'] ?? 0,
        'influencerId' => $data['id'] ?? '',
        'userLocation' => json_decode($info['location'], true),
        'userTags' => json_decode($info['tags'], true),
        'userLocalBiography' => $info['biography'] ?? null,
        'influencerRating' => $info['averageRating'] ?? 0,
    ];
}

function getAllInfluencersInstaGramuserNamesAndIdsByQuery($data, $cursor = null, $limit = 20)
{
    require_once "../../conn/Wamp64Connection.php";
    $connToUsuarios = getWamp64Connection("Users");
    $connToAccType = getWamp64Connection("userAccType");


    //DataBase: Users: table: userprofile

    //Column: userBirthdate type: DATE 2007-02-27
    //Return all influencers with this ge ranges
    //$selectedAges = $data['selectedAges'] ?? []; 

    //Column: userLocation
    // $selectedCities = $data['selectedCities'] ?? []; 
    /**
     * json structure in DB:
     * {
     *"userCity": null,
     *"isSetuped": false, //to check if the selectedCities are setuped
     *"userState": null,
     *"userCountry": null
     *}
     */

    // $selectedStates = $data['selectedStates'] ?? [];
    /**
     * json structure in DB:
     * {
     *"userCity": null,
     *"isSetuped": false, //to check if the selectedStates are setuped
     *"userState": null,
     *"userCountry": null
     *}
     */

    //Column: userTags 
    // $selectedNiches = $data['selectedNiches'] ?? []; /*tags is the same proposal to Niches */
    /**
     * json structure in DB:
     *{
     *"firstTag": "Pets",
     *"thirdTag": "Bulldog",
     *"isSetuped": true, //to check if the selectedNiches are setuped
     *"secondTag": "Rottweiler"
     */


    //DataBase: userAccType: Table: influencers

    //Column: ContentTypes
    // $selectedContentTypes = $data['selectedContentTypes'] ?? [];
    /**
     * json structure in DB:
     *{
     *"reels": true,
     *"stories": true,
     *"isSetuped": true, //to check if the ContentTypes are setuped
     *"videos": false
     *"posts": false
     */

    //Column: ContentPrices
    // $selectedPrices = $data['selectedPrices'] ?? ['max' => 0.0, 'min' => 0.0];
    //if user as selected ContentTypes like reels and stories, we gona return influencers who match the min and max range selectedPrices
    /**
     * json structure in DB:
     *{
     *"reels": {
        *min: 10,
        *max: 100,
     *},
     *"stories":  {
        *min: 10,
        *max: 100,
     *},
     *"isSetuped": true, //to check if the selectedPrices are setuped
     *"videos":  {
        *min: 10,
        *max: 100,
     *},
     *"posts":  {
        *min: 10,
        *max: 100,
     *},
     */
    
    // $cursor = isset($data['cursor']) && $data['cursor'] !== 'null' ? $data['cursor'] : null;


    $isSearchingFromSearchView = $search["isSearchingFromSearchiew"];
    $searchQuery = trim(strtolower($search["searchQuery"] ?? ""));

    $filteredIds = [];

    if ($isSearchingFromSearchView) {
        // Construindo a consulta para `Usuarios`
        $queryUsuarios = "
            SELECT graphInfluencerId
            FROM UserProfile
            WHERE (
                LOWER(userMutableName) LIKE CONCAT('%', LOWER(?), '%')
                OR LOWER(userBiography) LIKE CONCAT('%', LOWER(?), '%')
                OR (
                    JSON_EXTRACT(userTags, '$.isSetuped') = true
                    AND (
                        LOWER(JSON_EXTRACT(userTags, '$.firstTag')) LIKE CONCAT('%', LOWER(?), '%')
                        OR LOWER(JSON_EXTRACT(userTags, '$.secondTag')) LIKE CONCAT('%', LOWER(?), '%')
                        OR LOWER(JSON_EXTRACT(userTags, '$.thirdTag')) LIKE CONCAT('%', LOWER(?), '%')
                    )
                )
            )
        ";


        if ($cursor) {
            $queryUsuarios .= " AND graphInfluencerId > ? ";
        }

        $queryUsuarios .= "ORDER BY graphInfluencerId ASC LIMIT ?";

        $stmtUsuarios = $connToUsuarios->prepare($queryUsuarios);

        if ($cursor) {
            $stmtUsuarios->bind_param("sssssis", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery, $cursor, $limit);
        } else {
            $stmtUsuarios->bind_param("sssssi", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery, $limit);
        }

        if ($stmtUsuarios->execute()) {
            $resultUsuarios = $stmtUsuarios->get_result();
            while ($row = $resultUsuarios->fetch_assoc()) {
                error_log("Fetched graphInfluencerId: " . $row['graphInfluencerId']);
                $filteredIds[] = $row['graphInfluencerId'];
            }
        } else {
            error_log("Error executing query for UserProfile: " . $stmtUsuarios->error);
        }
        $stmtUsuarios->close();
    }

    if (empty($filteredIds)) {
        return ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false];
    }

    // Construir a lista de placeholders dinamicamente
    $placeholders = implode(",", array_fill(0, count($filteredIds), "?"));

    // Obter os nomes de usuários do banco `userAccType`
    $queryUsernames = "
        SELECT graphInfluencerUserName, graphInfluencerId
        FROM Influencers
        WHERE graphInfluencerId IN ($placeholders)
        ORDER BY graphInfluencerId ASC LIMIT ?
    ";

    $stmtUsernames = $connToAccType->prepare($queryUsernames);

    // Criar dinamicamente os tipos e os argumentos para o bind_param
    $types = str_repeat("s", count($filteredIds)) . "i";
    $params = array_merge($filteredIds, [$limit]);

    // Usar call_user_func_array para vincular os parâmetros dinamicamente
    $stmtUsernames->bind_param($types, ...$params);

    $resultData = ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false];

    if ($stmtUsernames->execute()) {
        $resultUsernames = $stmtUsernames->get_result();
        while ($row = $resultUsernames->fetch_assoc()) {
            $resultData["graphInfluencerUserName"][] = $row['graphInfluencerUserName'];
            $resultData["graphInfluencerId"][] = $row['graphInfluencerId'];
        }
    } else {
        error_log("Error executing query for Influencers: " . $stmtUsernames->error);
    }
    $stmtUsernames->close();

    // Verificar se há mais elementos disponíveis
    $lastId = end($resultData["graphInfluencerId"]);

    if ($lastId) {
        $queryCheck = "
            SELECT 1
            FROM UserProfile
            WHERE (
                LOWER(userMutableName) LIKE CONCAT('%', LOWER(?), '%') 
                OR LOWER(userBiography) LIKE CONCAT('%', LOWER(?), '%')
            )
            AND graphInfluencerId > ?
            LIMIT 1
        ";
        $stmtCheck = $connToUsuarios->prepare($queryCheck);
        $stmtCheck->bind_param("sss", $searchQuery, $searchQuery, $lastId);

        if ($stmtCheck->execute()) {
            $stmtCheck->store_result();
            if ($stmtCheck->num_rows > 0) {
                $resultData["hasMoreItems"] = true;
                $resultData["nextCursor"] = $lastId;
            } else {
                $resultData["hasMoreItems"] = false;
                $resultData["nextCursor"] = null;
            }
        } else {
            error_log("Error executing check query: " . $stmtCheck->error);
            $resultData["nextCursor"] = null;
        }
        $stmtCheck->close();
    } else {
        $resultData["nextCursor"] = null;
    }

    $connToUsuarios->close();
    $connToAccType->close();

    return $resultData;
}

function getAllInfluencersInstaGramuserNamesAndIds($conn, $cursor = null, $limit = 10)
{
    $query = "SELECT graphInfluencerUserName, graphInfluencerId 
              FROM Influencers ";
    if ($cursor) {
        $query .= "WHERE graphInfluencerId > ? ";
    }
    $query .= "ORDER BY graphInfluencerId ASC LIMIT ?";

    $stmt = $conn->prepare($query);

    if ($cursor) {
        $stmt->bind_param("si", $cursor, $limit);
    } else {
        $stmt->bind_param("i", $limit);
    }

    $resultData = ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false];

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $resultData["graphInfluencerUserName"][] = $row['graphInfluencerUserName'];
            $resultData["graphInfluencerId"][] = $row['graphInfluencerId'];
        }

        // Verifica se há mais itens
        $queryCheck = "SELECT 1 FROM Influencers WHERE graphInfluencerId > ? ORDER BY graphInfluencerId ASC LIMIT 1";
        $stmtCheck = $conn->prepare($queryCheck);
        $lastId = end($resultData["graphInfluencerId"]);

        if ($lastId) {
            $stmtCheck->bind_param("s", $lastId);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            if ($stmtCheck->num_rows > 0) {
                $resultData["hasMoreItems"] = true;
            }
            $stmtCheck->close();
        }

        $stmt->close();
    }

    $conn->close();

    return $resultData;
}

function getInfluencersInfoBatch($arrayGraphInfluencerIds)
{
    if (empty($arrayGraphInfluencerIds)) {
        return [];
    }

    require_once "../../conn/Wamp64Connection.php";

    $connUserProfile = getWamp64Connection("users");
    $connReviews = getWamp64Connection("userAccType");

    $idList = implode(',', array_map(fn($id) => "'$id'", $arrayGraphInfluencerIds));
    $queryProfile = "SELECT graphInfluencerId, userLocation, userTags, userBiography 
                     FROM userProfile WHERE graphInfluencerId IN ($idList)";
    $queryRating = "SELECT influencerId, AVG(rating) AS averageRating 
                    FROM influencerreview WHERE influencerId IN ($idList) 
                    GROUP BY influencerId";

    $info = [];

    $resultProfile = $connUserProfile->query($queryProfile);
    while ($row = $resultProfile->fetch_assoc()) {
        $info[$row['graphInfluencerId']] = [
            'location' => $row['userLocation'],
            'tags' => $row['userTags'] ?? '',
            'biography' => $row['userBiography'] ?? null,
        ];
    }

    $resultRating = $connReviews->query($queryRating);
    while ($row = $resultRating->fetch_assoc()) {
        $info[$row['influencerId']]['averageRating'] = round($row['averageRating'], 1);
    }

    return $info;
}

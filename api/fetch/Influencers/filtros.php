<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/home/filtros.log');
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../../logs/home/filtros.log';


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
$allowedHosts = ['10.0.2.2', 'localhost', '127.0.0.1'];
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

function getAllInfluencersInstaGramuserNamesAndIdsByQuery($data, $cursor = null, $limit = 20)
{
    $logFile = __DIR__ . '/../../logs/home/debug_filters.log';
    file_put_contents($logFile, "==== Iniciando filtro: " . date('Y-m-d H:i:s') . " ====\n", FILE_APPEND);
    file_put_contents($logFile, "Dados recebidos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    
    require_once "../../conn/Wamp64Connection.php";
    $connToUsuarios = getWamp64Connection("Users");
    $connToAccType = getWamp64Connection("userAccType");
    
    file_put_contents($logFile, "Conexões estabelecidas\n", FILE_APPEND);

    // Extrair os parâmetros de filtro
    $selectedAges = $data['selectedAges'] ?? [];
    $selectedCities = $data['selectedCities'] ?? [];
    $selectedStates = $data['selectedStates'] ?? [];
    $selectedNiches = $data['selectedNiches'] ?? [];
    $selectedContentTypes = $data['selectedContentTypes'] ?? [];
    $selectedPrices = $data['selectedPrices'] ?? ['max' => 0.0, 'min' => 0.0];
    $cursor = isset($data['cursor']) && $data['cursor'] !== 'null' ? $data['cursor'] : null;
    $isSearchingFromSearchView = $data['isSearchingFromSearchiew'] ?? false;
    $searchQuery = trim(strtolower($data['searchQuery'] ?? ""));
    
    file_put_contents($logFile, "Parâmetros extraídos:\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedAges: " . json_encode($selectedAges) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedCities: " . json_encode($selectedCities) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedStates: " . json_encode($selectedStates) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedNiches: " . json_encode($selectedNiches) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedContentTypes: " . json_encode($selectedContentTypes) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- selectedPrices: " . json_encode($selectedPrices) . "\n", FILE_APPEND);
    file_put_contents($logFile, "- cursor: " . ($cursor ?? 'null') . "\n", FILE_APPEND);
    file_put_contents($logFile, "- isSearchingFromSearchView: " . ($isSearchingFromSearchView ? 'true' : 'false') . "\n", FILE_APPEND);
    file_put_contents($logFile, "- searchQuery: " . $searchQuery . "\n", FILE_APPEND);

    // Iniciar a consulta base para UserProfile
    $queryUsuarios = "SELECT graphInfluencerId FROM UserProfile WHERE 1=1";
    $params = [];
    $types = "";

    // Filtrar por idade se selecionado
    if (!empty($selectedAges)) {
        $queryUsuarios .= " AND (";
        $ageConditions = [];

        foreach ($selectedAges as $index => $ageRange) {
            // Assumindo que $ageRange seja um array com 'min' e 'max'
            $minAge = $ageRange['min'] ?? 0;
            $maxAge = $ageRange['max'] ?? 100;

            $ageConditions[] = "(YEAR(CURDATE()) - YEAR(userBirthdate) BETWEEN ? AND ?)";
            $params[] = $minAge;
            $params[] = $maxAge;
            $types .= "ii";
        }

        $queryUsuarios .= implode(" OR ", $ageConditions) . ")";
    }

    // Filtrar por cidades se selecionado
    if (!empty($selectedCities)) {
        $queryUsuarios .= " AND JSON_EXTRACT(userLocation, '$.isSetuped') = true AND (";
        $cityConditions = [];

        foreach ($selectedCities as $city) {
            $cityConditions[] = "JSON_EXTRACT(userLocation, '$.userCity') = ?";
            $params[] = $city;
            $types .= "s";
        }

        $queryUsuarios .= implode(" OR ", $cityConditions) . ")";
    }

    // Filtrar por estados se selecionado
    if (!empty($selectedStates)) {
        $queryUsuarios .= " AND JSON_EXTRACT(userLocation, '$.isSetuped') = true AND (";
        $stateConditions = [];

        foreach ($selectedStates as $state) {
            $stateConditions[] = "JSON_EXTRACT(userLocation, '$.userState') = ?";
            $params[] = $state;
            $types .= "s";
        }

        $queryUsuarios .= implode(" OR ", $stateConditions) . ")";
    }

    // Filtrar por nichos (tags) se selecionado
    if (!empty($selectedNiches)) {
        $queryUsuarios .= " AND JSON_EXTRACT(userTags, '$.isSetuped') = true AND (";
        $nicheConditions = [];

        foreach ($selectedNiches as $niche) {
            $nicheConditions[] = "JSON_EXTRACT(userTags, '$.firstTag') = ? OR JSON_EXTRACT(userTags, '$.secondTag') = ? OR JSON_EXTRACT(userTags, '$.thirdTag') = ?";
            $params[] = $niche;
            $params[] = $niche;
            $params[] = $niche;
            $types .= "sss";
        }

        $queryUsuarios .= implode(" OR ", $nicheConditions) . ")";
    }

    // Adicionar filtro de pesquisa se estiver no modo de pesquisa
    if ($isSearchingFromSearchView && !empty($searchQuery)) {
        $queryUsuarios .= " AND (
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
        )";

        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $params[] = $searchQuery;
        $types .= "sssss";
    }

    // Adicionar filtro de cursor se existir
    if ($cursor) {
        $queryUsuarios .= " AND graphInfluencerId > ?";
        $params[] = $cursor;
        $types .= "s";
    }

    // Ordenar e limitar os resultados
    $queryUsuarios .= " ORDER BY graphInfluencerId ASC LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    // Log da query completa do UserProfile
    file_put_contents($logFile, "Query UserProfile: " . $queryUsuarios . "\n", FILE_APPEND);
    file_put_contents($logFile, "Params: " . json_encode($params) . "\n", FILE_APPEND);
    file_put_contents($logFile, "Types: " . $types . "\n", FILE_APPEND);

    // Executar a consulta para UserProfile
    $stmtUsuarios = $connToUsuarios->prepare($queryUsuarios);

    if ($stmtUsuarios) {
        // Vincular os parâmetros dinamicamente
        if (!empty($params)) {
            $stmtUsuarios->bind_param($types, ...$params);
        }

        $filteredIds = [];

        if ($stmtUsuarios->execute()) {
            $resultUsuarios = $stmtUsuarios->get_result();
            while ($row = $resultUsuarios->fetch_assoc()) {
                $filteredIds[] = $row['graphInfluencerId'];
            }
            file_put_contents($logFile, "Query UserProfile executada com sucesso\n", FILE_APPEND);
            file_put_contents($logFile, "IDs filtrados (primeira etapa): " . json_encode($filteredIds) . "\n", FILE_APPEND);
            file_put_contents($logFile, "Total de IDs encontrados: " . count($filteredIds) . "\n", FILE_APPEND);
        } else {
            $error = $stmtUsuarios->error;
            file_put_contents($logFile, "Erro ao executar query UserProfile: " . $error . "\n", FILE_APPEND);
            error_log("Error executing query for UserProfile: " . $error);
        }

        $stmtUsuarios->close();
    } else {
        $error = $connToUsuarios->error;
        file_put_contents($logFile, "Erro ao preparar query UserProfile: " . $error . "\n", FILE_APPEND);
        error_log("Error preparing query for UserProfile: " . $error);
    }

    // Se não houver IDs filtrados, retornar resultado vazio
    if (empty($filteredIds)) {
        file_put_contents($logFile, "Sem IDs filtrados na primeira etapa, retornando array vazio\n", FILE_APPEND);
        return ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false, "nextCursor" => null];
    }

    // Vamos verificar se os IDs existem na tabela Influencers antes de continuar
    $testQuery = "SELECT COUNT(*) as count FROM Influencers";
    $testStmt = $connToAccType->prepare($testQuery);
    if ($testStmt && $testStmt->execute()) {
        $testResult = $testStmt->get_result();
        $testRow = $testResult->fetch_assoc();
        file_put_contents($logFile, "Total de registros na tabela Influencers: " . $testRow['count'] . "\n", FILE_APPEND);
        $testStmt->close();
    }

    // Filtrar os resultados por ContentTypes e ContentPrices na tabela Influencers
    $placeholders = implode(",", array_fill(0, count($filteredIds), "?"));
    $queryInfluencers = "SELECT graphInfluencerId FROM Influencers WHERE graphInfluencerId IN ($placeholders)";
    $influencerParams = $filteredIds;
    $influencerTypes = str_repeat("s", count($filteredIds));

    // Filtrar por tipos de conteúdo se selecionado
    if (!empty($selectedContentTypes)) {
        $contentTypeConditions = [];
        foreach ($selectedContentTypes as $type) {
            $lowerType = strtolower($type);
            if ($lowerType === "reel") $lowerType = "reels";
            if ($lowerType === "story") $lowerType = "stories";
            if ($lowerType === "video") $lowerType = "videos";
            if ($lowerType === "post") $lowerType = "posts";

            $contentTypeConditions[] = "JSON_EXTRACT(ContentTypes, '$.$lowerType') = true";
        }

        if (!empty($contentTypeConditions)) {
            $queryInfluencers .= " AND JSON_EXTRACT(ContentTypes, '$.isSetuped') = true AND (" . implode(" OR ", $contentTypeConditions) . ")";
        }
    }

    // Filtrar por preços se selecionado
    if (isset($selectedPrices['min']) && $selectedPrices['min'] > 0 || isset($selectedPrices['max']) && $selectedPrices['max'] > 0) {
        $priceConditions = [];

        if (!empty($selectedContentTypes)) {
            foreach ($selectedContentTypes as $type) {
                $lowerType = strtolower($type);
                if ($lowerType === "reel") $lowerType = "reels";
                if ($lowerType === "story") $lowerType = "stories";
                if ($lowerType === "video") $lowerType = "videos";
                if ($lowerType === "post") $lowerType = "posts";

                $min = $selectedPrices['min'] ?? 0;
                $max = $selectedPrices['max'] ?? PHP_FLOAT_MAX;

                if ($max > 0) {
                    $priceConditions[] = "(JSON_EXTRACT(ContentPrices, '$.$lowerType.min') >= ? AND JSON_EXTRACT(ContentPrices, '$.$lowerType.max') <= ?)";
                    $influencerParams[] = $min;
                    $influencerParams[] = $max;
                    $influencerTypes .= "dd";
                }
            }

            if (!empty($priceConditions)) {
                $queryInfluencers .= " AND JSON_EXTRACT(ContentPrices, '$.isSetuped') = true AND (" . implode(" OR ", $priceConditions) . ")";
            }
        }
    }

    // Log da query completa do Influencers
    file_put_contents($logFile, "Query Influencers: " . $queryInfluencers . "\n", FILE_APPEND);
    file_put_contents($logFile, "Total de parâmetros para Influencers: " . count($influencerParams) . "\n", FILE_APPEND);
    
    // Executar a consulta para Influencers
    $stmtInfluencers = $connToAccType->prepare($queryInfluencers);
    $finalFilteredIds = [];

    if ($stmtInfluencers) {
        if (!empty($influencerParams)) {
            $stmtInfluencers->bind_param($influencerTypes, ...$influencerParams);
        }

        if ($stmtInfluencers->execute()) {
            $resultInfluencers = $stmtInfluencers->get_result();
            while ($row = $resultInfluencers->fetch_assoc()) {
                $finalFilteredIds[] = $row['graphInfluencerId'];
            }
            file_put_contents($logFile, "Query Influencers executada com sucesso\n", FILE_APPEND);
            file_put_contents($logFile, "IDs filtrados (segunda etapa): " . json_encode($finalFilteredIds) . "\n", FILE_APPEND);
            file_put_contents($logFile, "Total de IDs após segunda etapa: " . count($finalFilteredIds) . "\n", FILE_APPEND);
        } else {
            $error = $stmtInfluencers->error;
            file_put_contents($logFile, "Erro ao executar query Influencers: " . $error . "\n", FILE_APPEND);
            error_log("Error executing query for Influencers: " . $error);
        }

        $stmtInfluencers->close();
    } else {
        error_log("Error preparing query for Influencers: " . $connToAccType->error);
    }

    // Se não houver IDs após o segundo filtro, retornar resultado vazio
    if (empty($finalFilteredIds)) {
        return ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false, "nextCursor" => null];
    }

    // Obter os nomes de usuário para os IDs finais
    $placeholders = implode(",", array_fill(0, count($finalFilteredIds), "?"));
    $queryUsernames = "
        SELECT graphInfluencerUserName, graphInfluencerId
        FROM Influencers
        WHERE graphInfluencerId IN ($placeholders)
        ORDER BY graphInfluencerId ASC
    ";

    $stmtUsernames = $connToAccType->prepare($queryUsernames);
    $resultData = ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "hasMoreItems" => false, "nextCursor" => null];

    if ($stmtUsernames) {
        $usernameTypes = str_repeat("s", count($finalFilteredIds));
        $stmtUsernames->bind_param($usernameTypes, ...$finalFilteredIds);

        if ($stmtUsernames->execute()) {
            $resultUsernames = $stmtUsernames->get_result();
            while ($row = $resultUsernames->fetch_assoc()) {
                $resultData["graphInfluencerUserName"][] = $row['graphInfluencerUserName'];
                $resultData["graphInfluencerId"][] = $row['graphInfluencerId'];
            }
        } else {
            error_log("Error executing query for Influencers usernames: " . $stmtUsernames->error);
        }

        $stmtUsernames->close();
    } else {
        error_log("Error preparing query for Influencers usernames: " . $connToAccType->error);
    }

    // Verificar se há mais elementos disponíveis
    $lastId = end($resultData["graphInfluencerId"]);

    if ($lastId) {
        // Construir a consulta para verificar se há mais resultados
        $checkQuery = "SELECT 1 FROM UserProfile WHERE graphInfluencerId > ?";
        $checkParams = [$lastId];
        $checkTypes = "s";

        // Adicionar as mesmas condições da consulta principal
        $checkQueryParts = explode(" WHERE ", $queryUsuarios);
        if (count($checkQueryParts) > 1) {
            $conditions = explode(" ORDER BY ", $checkQueryParts[1])[0];
            $conditions = str_replace("graphInfluencerId > ?", "1=1", $conditions);
            $checkQuery .= " AND " . $conditions;

            // Adicionar os parâmetros relevantes
            array_shift($params); // Remover o limite
            if ($cursor) {
                array_pop($params); // Remover o cursor
            }

            $checkParams = array_merge($checkParams, $params);
            $checkTypes .= substr($types, 0, -1); // Remover o tipo do limite
            if ($cursor) {
                $checkTypes = substr($checkTypes, 0, -1); // Remover o tipo do cursor
            }
        }

        $checkQuery .= " LIMIT 1";

        $stmtCheck = $connToUsuarios->prepare($checkQuery);

        if ($stmtCheck) {
            $stmtCheck->bind_param($checkTypes, ...$checkParams);

            if ($stmtCheck->execute()) {
                $stmtCheck->store_result();
                if ($stmtCheck->num_rows > 0) {
                    $resultData["hasMoreItems"] = true;
                    $resultData["nextCursor"] = $lastId;
                }
            } else {
                error_log("Error executing check query: " . $stmtCheck->error);
            }

            $stmtCheck->close();
        } else {
            error_log("Error preparing check query: " . $connToUsuarios->error);
        }
    }

    $connToUsuarios->close();
    $connToAccType->close();

    return $resultData;
}

try {
    $response = getAllInfluencersInstaGramuserNamesAndIdsByQuery($data);
    echo json_encode($response);
} catch (\Throwable $th) {
    error_log(
        "ERRO CRÍTICO\n" .
            "Arquivo: " . $th->getFile() . "\n" .
            "Linha: " . $th->getLine() . "\n" .
            "Mensagem: " . $th->getMessage() . "\n" .
            "Stack Trace:\n" . $th->getTraceAsString() . "\n"
    );
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/home/error_HomeFiltersInfluencers.log');
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../../logs/home/error_HomeFiltersInfluencers.log';

// Inclui a função de conversão de estado
require_once "StateConverter.php";

// Inclui a função de debug de usuário específico
require_once "DebugSpecificInfluencer.php";

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

//logRequest($logFile);

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

require_once "../../conn/Wamp64Connection.php";
require_once "FetchCustomFilteredInfluencersInGraphAPI.php";

// ============= MODO DEBUG PARA USUÁRIO ESPECÍFICO =============
// Defina como true para testar apenas o usuário específico
$debugMode = false;
$specificUserId = "123456789_0";

if ($debugMode) {
    error_log("MODO DEBUG ATIVADO para usuário específico: $specificUserId");
    $debugResults = debugSpecificUser($data, $specificUserId);

    // Retorna os resultados de debug ao cliente
    echo json_encode([
        'debugMode' => true,
        'userTested' => $specificUserId,
        'debugResults' => $debugResults,
        'data' => $debugResults['filtersPassed'] ?
            [
                'graphInfluencerUserName' => [$specificUserId],
                'graphInfluencerId' => [$specificUserId]
            ] :
            [
                'graphInfluencerUserName' => [],
                'graphInfluencerId' => []
            ],
        'nextCursor' => null
    ]);
    exit;
}

// ============= CÓDIGO NORMAL CONTINUA ABAIXO =============

$conn = getWamp64Connection("userAccType");

if (!$conn) {
    die("Erro: Falha na conexão com o banco de dados.");
}

try {
    // Inicializa a resposta final focada nos influenciadores
    $finalResponse = [
        'data' => [], // Container principal que armazenará influencers por ID
        'total_influencers' => 0,
        'nextCursor' => null,
        'hasMoreItems' => false
    ];

    $cursor = isset($data['cursor']) ? $data['cursor'] : null;
    $response = getAllInfluencersInstaGramuserNamesAndIdsByQuery($data, $cursor);

    // Anexa informações de paginação à resposta
    $finalResponse['nextCursor'] = $response['nextCursor'];
    $finalResponse['hasMoreItems'] = $response['hasMoreItems'] ?? false;

    // Armazena os arrays de IDs e usernames para processamento
    $graphInfluencerUserNameArrays = $response['graphInfluencerUserName'] ?? [];
    $graphInfluencerIdsArrays = $response['graphInfluencerId'] ?? [];

    // Se existem influenciadores para processar
    if (!empty($graphInfluencerUserNameArrays)) {
        // Extrair filtro de seguidores do payload
        $selectedFollowers = $data['selectedFollowers'] ?? ['min' => 0, 'max' => 0];
        
        // Log para debug do filtro de seguidores
        error_log("Aplicando filtro de seguidores (vindo do payload do usuário): min=" . $selectedFollowers['min'] . ", max=" . $selectedFollowers['max']);
        
        // Busca os dados da API, passando o filtro de seguidores
        $queredInfluencers = fetchInfluencersData($graphInfluencerUserNameArrays, $graphInfluencerIdsArrays, $selectedFollowers);

        // Adiciona as avaliações aos dados dos influenciadores
        $queredInfluencers = addInfluencerRatings($queredInfluencers);

        // Obter dados de localização, tags e preços para cada influenciador
        // Passa os tipos de conteúdo selecionados para filtrar os preços
        $selectedContentTypes = $data['selectedContentTypes'] ?? [];
        $influencerInfos = getInfluencersInfoFromDB($queredInfluencers, $selectedContentTypes);

        // Formatar os dados para o formato esperado pelo frontend
        $dataById = []; // Array associativo para armazenar por ID
        
        foreach ($queredInfluencers['data'] as $index => $influencer) {
            $apiData = $influencer['apiData'] ?? [];
            $influencerId = $influencer['graphInfluencerId'];
            $info = $influencerInfos[$influencerId] ?? [
                'location' => '{}',
                'tags' => '{}',
                'userBiography' => '',
                'averageRating' => $influencer['averageRating'] ?? 0,
                'prices' => [],
                'averageMinPrice' => 0
            ];

            $parsedData = parseJsonToDataAllInfluencers($apiData, $info);
            
            // Adiciona ao array associativo usando o ID como chave
            $dataById[$influencerId] = $parsedData;
        }

        // Adiciona os dados formatados como elementos da resposta
        $finalResponse['data'] = $dataById;
        $finalResponse['total_influencers'] = count($dataById);
    }

    // Envia a resposta para o cliente
    echo json_encode($finalResponse);

    // Log da resposta final para debug
    error_log("Resposta final formatada: " . json_encode([
        'total_influencers' => $finalResponse['total_influencers'], 
        'data' => $finalResponse['data'],
        'nextCursor' => $finalResponse['nextCursor'],
        'hasMoreItems' => $finalResponse['hasMoreItems']
    ], JSON_PRETTY_PRINT));

    if ($conn) {
        $conn->close();
    }
} catch (\Throwable $th) {
    error_log("Ocorreu um erro: " . $th->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Obtém informações adicionais dos influenciadores do banco de dados
 * 
 * @param array $influencersData Dados dos influenciadores obtidos via API
 * @param array $selectedContentTypes Tipos de conteúdo selecionados pelo usuário
 * @return array Array associativo com informações por ID de influenciador
 */
function getInfluencersInfoFromDB($influencersData, $selectedContentTypes = [])
{
    if (empty($influencersData['data'])) {
        return [];
    }

    // Obtém conexão com o banco de dados
    $connToUsuarios = getWamp64Connection("Users");
    $connToAccType = getWamp64Connection("UserAccType");

    if (!$connToUsuarios || !$connToAccType) {
        error_log("Erro: Falha na conexão com o banco de dados ao buscar informações dos influenciadores");
        return [];
    }

    // Extrai os IDs dos influenciadores
    $influencerIds = array_map(function ($item) {
        return $item['graphInfluencerId'];
    }, $influencersData['data']);

    // Prepara a lista de IDs para a query
    $idList = "'" . implode("','", array_map([$connToUsuarios, 'real_escape_string'], $influencerIds)) . "'";

    // Query para obter dados de perfil (adicionando userBirthdate)
    $profileQuery = "SELECT 
        graphInfluencerId, 
        userTags, 
        userLocation, 
        userBiography,
        userBirthdate
    FROM UserProfile 
    WHERE graphInfluencerId IN ($idList)";

    // Query para obter preços
    $pricesQuery = "SELECT 
        graphInfluencerId, 
        ContentPrices 
    FROM Influencers 
    WHERE graphInfluencerId IN ($idList)";

    // Executa as queries
    $profileResult = $connToUsuarios->query($profileQuery);
    $pricesResult = $connToAccType->query($pricesQuery);

    if (!$profileResult) {
        error_log("Erro ao buscar perfis: " . $connToUsuarios->error);
    }

    if (!$pricesResult) {
        error_log("Erro ao buscar preços: " . $connToAccType->error);
    }

    // Organiza os dados de perfil por ID
    $infoMap = [];
    if ($profileResult) {
        while ($row = $profileResult->fetch_assoc()) {
            $infoMap[$row['graphInfluencerId']] = [
                'tags' => $row['userTags'],
                'location' => $row['userLocation'],
                'userBiography' => $row['userBiography'],
                'userBirthdate' => $row['userBirthdate'], // Adicionando a data de nascimento
                'prices' => []  // Será preenchido com os preços
            ];
        }
    }

    // Adiciona os preços às informações
    if ($pricesResult) {
        while ($row = $pricesResult->fetch_assoc()) {
            $id = $row['graphInfluencerId'];
            if (isset($infoMap[$id])) {
                $contentPrices = json_decode($row['ContentPrices'], true);

                // Processa apenas os tipos de conteúdo selecionados
                $filteredPrices = [];
                if (!empty($selectedContentTypes) && isset($contentPrices['isSetuped']) && $contentPrices['isSetuped']) {
                    foreach ($selectedContentTypes as $contentType) {
                        $contentKey = '';

                        switch (strtolower($contentType)) {
                            case 'stories':
                                $contentKey = 'stories';
                                break;
                            case 'reel':
                            case 'reels':
                                $contentKey = 'reels';
                                break;
                            case 'post':
                            case 'posts':
                                $contentKey = 'posts';
                                break;
                            case 'video':
                            case 'videos':
                                $contentKey = 'videos';
                                break;
                        }

                        if (!empty($contentKey) && isset($contentPrices[$contentKey])) {
                            $filteredPrices[$contentKey] = $contentPrices[$contentKey];
                        }
                    }
                } else if (isset($contentPrices['isSetuped']) && $contentPrices['isSetuped']) {
                    // Se não há tipos de conteúdo selecionados, inclui todos
                    $priceKeys = ['posts', 'reels', 'videos', 'stories'];
                    foreach ($priceKeys as $key) {
                        if (isset($contentPrices[$key])) {
                            $filteredPrices[$key] = $contentPrices[$key];
                        }
                    }
                }

                $infoMap[$id]['prices'] = $filteredPrices;

                // Calcula a média de preço mínimo para os tipos de conteúdo selecionados
                $minPrices = array_column($filteredPrices, 'min');
                $avgMinPrice = !empty($minPrices) ? array_sum($minPrices) / count($minPrices) : 0;
                $infoMap[$id]['averageMinPrice'] = round($avgMinPrice, 2);
            }
        }
    }

    // Adiciona avaliações médias às informações
    $ratingsMap = getInfluencerRatings($influencerIds);

    // Combina os dados de perfil e avaliações
    foreach ($infoMap as $id => &$info) {
        $info['averageRating'] = $ratingsMap[$id] ?? 0;
    }

    $connToUsuarios->close();
    $connToAccType->close();
    return $infoMap;
}

/**
 * Adiciona as avaliações médias aos dados dos influenciadores
 * 
 * @param array $influencersData Dados dos influenciadores obtidos via API
 * @return array Dados completos incluindo avaliações médias
 */
function getInfluencerRatings($influencerIds)
{
    if (empty($influencerIds)) {
        return [];
    }

    // Obtém conexão com o banco de dados
    $conn = getWamp64Connection("userAccType");

    if (!$conn) {
        error_log("Erro: Falha na conexão com o banco de dados ao buscar avaliações");
        return [];
    }

    // Prepara a lista de IDs para a query
    $idList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $influencerIds)) . "'";

    // Query para obter as avaliações médias
    $queryRating = "SELECT influencerId, AVG(rating) AS averageRating
                    FROM influencerreview WHERE influencerId IN ($idList)
                    GROUP BY influencerId";

    // Executa a query
    $result = $conn->query($queryRating);

    if (!$result) {
        error_log("Erro ao buscar avaliações: " . $conn->error);
        $conn->close();
        return [];
    }

    // Cria um mapa de ID -> avaliação média
    $ratingsMap = [];
    while ($row = $result->fetch_assoc()) {
        $ratingsMap[$row['influencerId']] = (float)$row['averageRating'];
    }

    $conn->close();
    return $ratingsMap;
}

function addInfluencerRatings($influencersData)
{
    if (empty($influencersData['data'])) {
        return $influencersData;
    }

    // Extrai os IDs dos influenciadores
    $influencerIds = array_map(function ($item) {
        return $item['graphInfluencerId'];
    }, $influencersData['data']);

    // Obtém as avaliações
    $ratingsMap = getInfluencerRatings($influencerIds);

    // Adiciona as avaliações médias aos dados dos influenciadores
    foreach ($influencersData['data'] as $key => $influencer) {
        $id = $influencer['graphInfluencerId'];
        $influencersData['data'][$key]['averageRating'] = $ratingsMap[$id] ?? 0;
    }

    return $influencersData;
}

function getAllInfluencersInstaGramuserNamesAndIdsByQuery($filterItems, $cursor = null, $limit = 20)
{
    error_log("=========== INÍCIO DA EXECUÇÃO (NOVA ABORDAGEM) ===========");
   // error_log("Filtros recebidos: " . json_encode($filterItems, JSON_PRETTY_PRINT));

    require_once "../../conn/Wamp64Connection.php";
    $connToUsuarios = getWamp64Connection("Users");
    $connToAccType = getWamp64Connection("UserAccType");

    error_log("Conexões estabelecidas com bancos 'Users' e 'UserAccType'");

    // Extrair parâmetros de filtro
    $cursor = isset($filterItems['cursor']) && $filterItems['cursor'] !== 'null' ? $filterItems['cursor'] : null;
    $selectedNiches = $filterItems['selectedNiches'] ?? [];
    $selectedAges = $filterItems['selectedAges'] ?? [];
    $selectedCities = $filterItems['selectedCities'] ?? [];
    $selectedContentTypes = $filterItems['selectedContentTypes'] ?? [];
    $selectedStates = $filterItems['selectedStates'] ?? [];
    $selectedFollowers = $filterItems['selectedFollowers'] ?? ['min' => 0, 'max' => 0];
    $selectedPrices = $filterItems['selectedPrices'] ?? ['min' => 0, 'max' => 0];

    error_log("Parâmetros de filtro extraídos:");
    error_log("- Cursor: " . ($cursor ?? "null"));
    error_log("- Nichos: " . json_encode($selectedNiches));
    error_log("- Idades: " . json_encode($selectedAges));
    error_log("- Cidades: " . json_encode($selectedCities));
    error_log("- Estados: " . json_encode($selectedStates));
    error_log("- Tipos de Conteúdo: " . json_encode($selectedContentTypes));
    error_log("- Seguidores (min/max): " . $selectedFollowers['min'] . "/" . $selectedFollowers['max']);
    error_log("- Preços (min/max): " . $selectedPrices['min'] . "/" . $selectedPrices['max']);

    // FASE 1: Obter batch de IDs iniciais
    error_log("======= FASE 1: OBTENDO BATCH DE IDs INICIAIS =======");

    // Vamos pegar um número maior de IDs para ter mais chances de encontrar matches após a filtragem
    $batchSize = $limit * 10;
    $batchQuery = "SELECT graphInfluencerId, userBiography FROM UserProfile";

    // Adicionar condição para o cursor
    if ($cursor) {
        $batchQuery .= " WHERE graphInfluencerId > ?";
        $cursorParam = [$cursor];
    } else {
        $cursorParam = [];
    }

    $batchQuery .= " ORDER BY graphInfluencerId ASC LIMIT ?";
    $params = array_merge($cursorParam, [$batchSize]);

    error_log("Query para obter batch inicial: " . $batchQuery);
    error_log("Parâmetros: " . json_encode($params));

    $stmt = $connToUsuarios->prepare($batchQuery);

    if ($stmt) {
        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            $stmt->bind_param($types, ...$params);
        }

        $candidateIds = [];

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $candidateIds[] = $row['graphInfluencerId'];
            }
            error_log("Obtidos " . count($candidateIds) . " IDs de candidatos do batch inicial");
        } else {
            error_log("ERRO ao executar query de batch: " . $stmt->error);
        }

        $stmt->close();
    } else {
        error_log("ERRO ao preparar statement de batch: " . $connToUsuarios->error);
    }

    if (empty($candidateIds)) {
        error_log("Nenhum ID de candidato encontrado. Retornando resultado vazio.");
        return ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "nextCursor" => null, "hasMoreItems" => false];
    }

    // FASE 2: Verificação individual de cada ID
    error_log("======= FASE 2: VERIFICANDO CADA ID INDIVIDUALMENTE =======");

    $filteredIds = [];
    $currentYear = date('Y');

    // Preparar queries para verificações individuais
    $userProfileQuery = "SELECT 
        graphInfluencerId, 
        userTags, 
        userLocation, 
        userBirthdate
    FROM UserProfile 
    WHERE graphInfluencerId = ?";

    $contentPricesQuery = "SELECT ContentPrices FROM Influencers WHERE graphInfluencerId = ?";

    // Preparar statements
    $stmtUserProfile = $connToUsuarios->prepare($userProfileQuery);
    $stmtContentPrices = $connToAccType->prepare($contentPricesQuery);

    if (!$stmtUserProfile || !$stmtContentPrices) {
        error_log("ERRO ao preparar statements para verificação individual");
        return ["graphInfluencerUserName" => [], "graphInfluencerId" => [], "nextCursor" => null, "hasMoreItems" => false];
    }

    foreach ($candidateIds as $candidateId) {
        // Iniciar assumindo que o candidato passa em todos os filtros
        $passesAllFilters = true;
        $filterLogs = [];

        // Obter dados do perfil do usuário
        $stmtUserProfile->bind_param("s", $candidateId);

        if ($stmtUserProfile->execute()) {
            $userProfileResult = $stmtUserProfile->get_result();
            $userData = $userProfileResult->fetch_assoc();

            if (!$userData) {
                // Perfil não encontrado, pular para o próximo candidato
                $filterLogs[] = "Perfil não encontrado";
                $passesAllFilters = false;
                continue;
            }

            // Decodificar campos JSON
            $userTags = json_decode($userData['userTags'], true);
            $userLocation = json_decode($userData['userLocation'], true);
            $userBirthdate = $userData['userBirthdate'];

            // Verificar nichos/tags
            if (!empty($selectedNiches) && $passesAllFilters) {
                $passesNichesFilter = false;

                if (isset($userTags['isSetuped']) && $userTags['isSetuped']) {
                    $firstTag = $userTags['firstTag'] ?? '';
                    $secondTag = $userTags['secondTag'] ?? '';
                    $thirdTag = $userTags['thirdTag'] ?? '';

                    foreach ($selectedNiches as $niche) {
                        if (
                            stripos($firstTag, $niche) !== false ||
                            stripos($secondTag, $niche) !== false ||
                            stripos($thirdTag, $niche) !== false
                        ) {
                            $passesNichesFilter = true;
                            $filterLogs[] = "Passou no filtro de nicho: Encontrado '$niche'";
                            break;
                        }
                    }
                }

                if (!$passesNichesFilter) {
                    $filterLogs[] = "Falhou no filtro de nichos";
                    $passesAllFilters = false;
                }
            }

            // Verificar idade
            if (!empty($selectedAges) && $passesAllFilters && $userBirthdate) {
                $birthYear = date('Y', strtotime($userBirthdate));
                $passesAgeFilter = false;

                foreach ($selectedAges as $ageRange) {
                    if (preg_match('/(\d+)-(\d+)/', $ageRange, $matches)) {
                        $minAge = $matches[1];
                        $maxAge = $matches[2];

                        $maxBirthYear = $currentYear - $minAge;
                        $minBirthYear = $currentYear - $maxAge;

                        if ($birthYear >= $minBirthYear && $birthYear <= $maxBirthYear) {
                            $passesAgeFilter = true;
                            $filterLogs[] = "Passou no filtro de idade: $ageRange";
                            break;
                        }
                    }
                }

                if (!$passesAgeFilter) {
                    $filterLogs[] = "Falhou no filtro de idade, ano nascimento: $birthYear";
                    $passesAllFilters = false;
                }
            }

            // Verificar cidade
            if (!empty($selectedCities) && $passesAllFilters) {
                $passesCityFilter = false;
                $userCity = strtolower($userLocation['userCity'] ?? '');

                foreach ($selectedCities as $city) {
                    if (strtolower($city) === $userCity) {
                        $passesCityFilter = true;
                        $filterLogs[] = "Passou no filtro de cidade: $city";
                        break;
                    }
                }

                if (!$passesCityFilter) {
                    $filterLogs[] = "Falhou no filtro de cidade, cidade do usuário: $userCity";
                    $passesAllFilters = false;
                }
            }

            // Verificar estado
            if (!empty($selectedStates) && $passesAllFilters) {
                $passesStateFilter = false;
                $userState = strtolower($userLocation['userState'] ?? '');

                foreach ($selectedStates as $stateAbbr) {
                    $stateFullName = convertStateAbbreviationToFullName($stateAbbr);
                    $stateFullNameLower = strtolower($stateFullName ?? $stateAbbr);

                    if ($stateFullNameLower === $userState) {
                        $passesStateFilter = true;
                        $filterLogs[] = "Passou no filtro de estado: $stateAbbr -> $stateFullName";
                        break;
                    }
                }

                if (!$passesStateFilter) {
                    $filterLogs[] = "Falhou no filtro de estado, estado do usuário: $userState";
                    $passesAllFilters = false;
                }
            }

            // Ignorar verificação de seguidores
            // Os seguidores serão obtidos posteriormente via API do Facebook
            if (($selectedFollowers['min'] > 0 || $selectedFollowers['max'] > 0) && $passesAllFilters) {
                $filterLogs[] = "Filtro de seguidores ignorado - será processado posteriormente via API";
            }

            // Verificar preço
            if (($selectedPrices['min'] > 0 || $selectedPrices['max'] > 0) && $passesAllFilters) {
                $stmtContentPrices->bind_param("s", $candidateId);

                if ($stmtContentPrices->execute()) {
                    $contentPricesResult = $stmtContentPrices->get_result();
                    $contentPricesData = $contentPricesResult->fetch_assoc();

                    if (!$contentPricesData) {
                        $filterLogs[] = "Falhou no filtro de preço: Dados de preço não encontrados";
                        $passesAllFilters = false;
                    } else {
                        $contentPrices = json_decode($contentPricesData['ContentPrices'], true);
                        $passesPriceFilter = false;

                        // Verificar se o ContentPrices está configurado
                        if (isset($contentPrices['isSetuped']) && $contentPrices['isSetuped']) {
                            // Verificar os preços para cada tipo de conteúdo
                            $priceTypes = ['posts', 'reels', 'videos', 'stories'];

                            foreach ($priceTypes as $priceType) {
                                if (isset($contentPrices[$priceType])) {
                                    $typeMinPrice = $contentPrices[$priceType]['min'] ?? 0;
                                    $typeMaxPrice = $contentPrices[$priceType]['max'] ?? 0;

                                    // Se o preço mínimo do usuário está dentro da faixa solicitada
                                    if (
                                        $selectedPrices['min'] <= $typeMinPrice &&
                                        ($selectedPrices['max'] == 0 || $typeMinPrice <= $selectedPrices['max'])
                                    ) {
                                        $passesPriceFilter = true;
                                        $filterLogs[] = "Passou no filtro de preço para $priceType: min=$typeMinPrice, max=$typeMaxPrice";
                                        break;
                                    }
                                }
                            }
                        }

                        if (!$passesPriceFilter) {
                            $filterLogs[] = "Falhou no filtro de preço";
                            $passesAllFilters = false;
                        }
                    }
                } else {
                    $filterLogs[] = "Erro ao consultar preços: " . $stmtContentPrices->error;
                    $passesAllFilters = false;
                }
            }

            // Verificar tipos de conteúdo
            if (!empty($selectedContentTypes) && $passesAllFilters) {
                $stmtContentPrices->bind_param("s", $candidateId);

                if ($stmtContentPrices->execute()) {
                    $contentTypesResult = $stmtContentPrices->get_result();
                    $contentData = $contentTypesResult->fetch_assoc();

                    if (!$contentData) {
                        $filterLogs[] = "Falhou no filtro de tipo de conteúdo: Dados não encontrados";
                        $passesAllFilters = false;
                    } else {
                        $contentPrices = json_decode($contentData['ContentPrices'], true);
                        $passesContentTypeFilter = false;

                        foreach ($selectedContentTypes as $contentType) {
                            $contentKey = '';

                            switch (strtolower($contentType)) {
                                case 'stories':
                                    $contentKey = 'stories';
                                    break;
                                case 'reel':
                                case 'reels':
                                    $contentKey = 'reels';
                                    break;
                                case 'post':
                                case 'posts':
                                    $contentKey = 'posts';
                                    break;
                                case 'video':
                                case 'videos':
                                    $contentKey = 'videos';
                                    break;
                            }

                            if (!empty($contentKey) && isset($contentPrices[$contentKey]) && is_array($contentPrices[$contentKey])) {
                                $passesContentTypeFilter = true;
                                $filterLogs[] = "Passou no filtro de tipo de conteúdo: $contentType";
                                break;
                            }
                        }

                        if (!$passesContentTypeFilter) {
                            $filterLogs[] = "Falhou no filtro de tipo de conteúdo";
                            $passesAllFilters = false;
                        }
                    }
                } else {
                    $filterLogs[] = "Erro ao consultar tipos de conteúdo: " . $stmtContentPrices->error;
                    $passesAllFilters = false;
                }
            }
        } else {
            $filterLogs[] = "Erro ao consultar perfil: " . $stmtUserProfile->error;
            $passesAllFilters = false;
        }

        // Registrar o resultado da verificação
        if ($passesAllFilters) {
            $filteredIds[] = $candidateId;
            error_log("ID $candidateId PASSOU em todos os filtros");

            // Se já temos IDs suficientes, podemos parar
            if (count($filteredIds) >= $limit) {
                error_log("Atingido limite de $limit IDs. Interrompendo verificação.");
                break;
            }
        } else {
            error_log("ID $candidateId FALHOU nos filtros: " . implode("; ", $filterLogs));
        }
    }

    $stmtUserProfile->close();
    $stmtContentPrices->close();

    error_log("======= FASE 3: OBTENDO USERNAMES PARA OS IDs FILTRADOS =======");
    error_log("Total de IDs que passaram nos filtros: " . count($filteredIds));

    if (empty($filteredIds)) {
        error_log("Nenhum ID passou nos filtros. Retornando resultado vazio.");
        return [
            "graphInfluencerUserName" => [],
            "graphInfluencerId" => [],
            "nextCursor" => null,
            "hasMoreItems" => false,
            "total" => 0
        ];
    }

    // Obter usernames para os IDs filtrados
    $placeholders = implode(',', array_fill(0, count($filteredIds), '?'));
    $usernamesQuery = "SELECT graphInfluencerUserName, graphInfluencerId FROM Influencers WHERE graphInfluencerId IN ($placeholders) ORDER BY graphInfluencerId ASC";

    $stmtUsernames = $connToAccType->prepare($usernamesQuery);
    $types = str_repeat('s', count($filteredIds));
    $stmtUsernames->bind_param($types, ...$filteredIds);

    $resultData = [
        "graphInfluencerUserName" => [],
        "graphInfluencerId" => [],
        "nextCursor" => null,
        "hasMoreItems" => false,
        "total" => 0
    ];

    if ($stmtUsernames->execute()) {
        $usernamesResult = $stmtUsernames->get_result();

        while ($row = $usernamesResult->fetch_assoc()) {
            $resultData["graphInfluencerUserName"][] = $row['graphInfluencerUserName'];
            $resultData["graphInfluencerId"][] = $row['graphInfluencerId'];
        }

        error_log("Obtidos " . count($resultData["graphInfluencerUserName"]) . " usernames");
    } else {
        error_log("ERRO ao obter usernames: " . $stmtUsernames->error);
    }

    $stmtUsernames->close();

    // Verificar se há mais resultados
    $lastId = end($resultData["graphInfluencerId"]);

    if ($lastId && count($filteredIds) >= $limit) {
        $resultData["hasMoreItems"] = true;
        $resultData["nextCursor"] = $lastId;
        error_log("Há mais resultados disponíveis. Definindo cursor: $lastId");
    }

    $connToUsuarios->close();
    $connToAccType->close();

    error_log("=========== FIM DA EXECUÇÃO (NOVA ABORDAGEM) ===========");
    error_log("Resultado final: " . json_encode([
        "total_usernames" => count($resultData["graphInfluencerUserName"]),
        "nextCursor" => $resultData["nextCursor"],
        "hasMoreItems" => $resultData["hasMoreItems"],
        "total" => count($resultData["graphInfluencerUserName"])
    ]));

    $resultData["total"] = count($resultData["graphInfluencerUserName"]);

    return $resultData;
}

function parseJsonToDataAllInfluencers($data, $info)
{
    return [
        'influencerName' => $data['name'] ?? '', //from instagram API
        'influencerDescription' => $data['biography'] ?? '',  //from instagram API
        'influencerUserName' => $data['username'] ?? '', //from instagram API
        'influencerProfileURL' => $data['profile_picture_url'] ?? null, //from instagram API
        'influencerFollowers' => $data['followers_count'] ?? 0, //from instagram API
        'influencerFollowing' => $data['follows_count'] ?? 0, //from instagram API
        'influencerId' => $data['id'] ?? '', //from instagram API
        'userLocation' => json_decode($info['location'], true),
        'userTags' => json_decode($info['tags'], true),
        'userLocalBiography' => $info['userBiography'] ?? null,
        'userBirthdate' => $info['userBirthdate'] ?? null, // Adicionando a data de nascimento
        'influencerRating' => $info['averageRating'] ?? 0,
        'contentPrices' => $info['prices'] ?? [], // Preços dos tipos de conteúdo selecionados
        'averageMinPrice' => $info['averageMinPrice'] ?? 0 // Preço médio mínimo
    ];
}
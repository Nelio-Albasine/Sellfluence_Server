<?php
include '../../settings/config.php';

/**
 * Função para buscar dados de influenciadores via Graph API do Facebook
 * 
 * @param array $graphInfluencerUserNames Array com os nomes de usuário dos influenciadores
 * @param array $graphInfluencerIds Array com os IDs dos influenciadores (no mesmo índice dos nomes)
 * @param array $followersFilter Filtro de seguidores (min, max)
 * @return array Array com os dados obtidos dos influenciadores
 */
function fetchInfluencersData($graphInfluencerUserNames, $graphInfluencerIds, $followersFilter = null) {
    // Verifica se os arrays têm o mesmo tamanho
    $token = FACEBOOK_ACCESS_TOKEN;
    $accountId = FACEBOOK_ACCOUNT_ID;

    if (count($graphInfluencerUserNames) !== count($graphInfluencerIds)) {
        error_log("Erro: Os arrays de nomes de usuário e IDs devem ter o mesmo tamanho");
        return ['error' => 'Arrays mismatch', 'data' => []];
    }

    // Inicializa array para armazenar os resultados
    $results = [
        'success' => [],
        'errors' => [],
        'total_processed' => count($graphInfluencerUserNames),
        'total_success' => 0,
        'total_errors' => 0,
        'data' => []
    ];

    // Se não há influenciadores, retorna resultado vazio
    if (empty($graphInfluencerUserNames)) {
        error_log("Aviso: Nenhum nome de usuário de influenciador fornecido");
        return $results;
    }

    // Validação e log do filtro de seguidores
    $minFollowers = 0;
    $maxFollowers = PHP_INT_MAX;
    
    if ($followersFilter && is_array($followersFilter)) {
        if (isset($followersFilter['min']) && is_numeric($followersFilter['min']) && $followersFilter['min'] > 0) {
            $minFollowers = (int)$followersFilter['min'];
        }
        
        if (isset($followersFilter['max']) && is_numeric($followersFilter['max']) && $followersFilter['max'] > 0) {
            $maxFollowers = (int)$followersFilter['max'];
        }
        
        error_log("Aplicando filtro de seguidores: min={$minFollowers}, max=" . ($maxFollowers == PHP_INT_MAX ? "sem limite" : $maxFollowers));
    } else {
        error_log("Nenhum filtro de seguidores válido fornecido. Usando valores padrão: min=0, max=sem limite");
    }

    // Inicializa o manipulador multi CURL
    $mh = curl_multi_init();
    $curlMulti = [];

    // Configura as requisições para cada influenciador
    foreach ($graphInfluencerUserNames as $index => $influencerAtual) {
        // Limpa o nome de usuário para garantir que não haja caracteres problemáticos
        $influencerAtual = trim($influencerAtual);
        
        // Verifica se o nome de usuário não está vazio
        if (empty($influencerAtual)) {
            $results['errors'][] = [
                'index' => $index,
                'id' => $graphInfluencerIds[$index] ?? 'unknown',
                'username' => 'empty',
                'error' => 'Nome de usuário vazio'
            ];
            $results['total_errors']++;
            continue;
        }

        // Monta o endpoint da API
        $endpoint = "https://graph.facebook.com/v18.0/$accountId?fields=business_discovery.username($influencerAtual){username,website,name,ig_id,id,profile_picture_url,biography,follows_count,followers_count}&access_token=$token";
        
        // Inicializa e configura o CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true); // Inclui cabeçalhos na resposta
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos
        
        // Adiciona ao multi CURL
        $curlMulti[$index] = $ch;
        curl_multi_add_handle($mh, $ch);
    }

    // Executa as requisições em paralelo
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        
        // Evita uso excessivo de CPU
        if ($running) {
            curl_multi_select($mh, 0.1); // Pausa por 100ms
        }
    } while ($running && $status == CURLM_OK);

    // Processa os resultados
    foreach ($curlMulti as $index => $ch) {
        $rawResponse = curl_multi_getcontent($ch);
        $influencerId = $graphInfluencerIds[$index] ?? 'unknown';
        $username = $graphInfluencerUserNames[$index];
        
        // Remove o handle do multi CURL
        curl_multi_remove_handle($mh, $ch);
        
        // Separa cabeçalhos e corpo da resposta
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);
        
        // Verifica código de status HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            // Registra erro e continua para o próximo
            error_log("Erro na requisição cURL para o influenciador {$username}: HTTP $httpCode");
            error_log("Cabeçalhos: $headers");
            error_log("Corpo: $body");
            
            $errorMsg = "HTTP $httpCode";
            
            if ($httpCode === 401) {
                $errorMsg .= " - Token expirado ou inválido";
            } elseif ($httpCode === 403) {
                $errorMsg .= " - Acesso negado para o endpoint";
            } elseif ($httpCode === 400) {
                $errorMsg .= " - Requisição inválida";
            } elseif ($httpCode === 404) {
                $errorMsg .= " - Usuário não encontrado";
            }
            
            $results['errors'][] = [
                'index' => $index,
                'id' => $influencerId,
                'username' => $username,
                'error' => $errorMsg,
                'raw_response' => substr($body, 0, 500) // Limita o tamanho do log
            ];
            
            $results['total_errors']++;
            continue;
        }
        
        // Decodifica o JSON da resposta
        $responseData = json_decode($body, true);
        $businessDiscovery = $responseData['business_discovery'] ?? null;
        
        if (!$businessDiscovery) {
            // Registra erro se business_discovery não estiver presente
            error_log("Erro: Dados business_discovery não encontrados na resposta para {$username}");
            $results['errors'][] = [
                'index' => $index,
                'id' => $influencerId,
                'username' => $username,
                'error' => 'Dados business_discovery ausentes na resposta',
                'raw_response' => substr($body, 0, 500)
            ];
            
            $results['total_errors']++;
            continue;
        }
        
        // Verifica se o influenciador atende ao filtro de seguidores
        $followers = $businessDiscovery['followers_count'] ?? 0;
        $passesFollowersFilter = true;
        
        // Aplicação mais robusta do filtro de seguidores
        if ($minFollowers > 0 || $maxFollowers < PHP_INT_MAX) {
            if ($followers < $minFollowers) {
                $passesFollowersFilter = false;
                error_log("Influenciador {$username} NÃO atende ao filtro mínimo de seguidores: tem {$followers}, mínimo requerido: {$minFollowers}");
            } else if ($maxFollowers < PHP_INT_MAX && $followers > $maxFollowers) {
                $passesFollowersFilter = false;
                error_log("Influenciador {$username} NÃO atende ao filtro máximo de seguidores: tem {$followers}, máximo permitido: {$maxFollowers}");
            } else {
                error_log("Influenciador {$username} PASSOU no filtro de seguidores: tem {$followers}, range permitido: {$minFollowers} - " . ($maxFollowers == PHP_INT_MAX ? "sem limite" : $maxFollowers));
            }
        } else {
            error_log("Filtro de seguidores não aplicado para {$username}: tem {$followers} seguidores");
        }
        
        // Pula para o próximo se não atende ao filtro de seguidores
        if (!$passesFollowersFilter) {
            $results['errors'][] = [
                'index' => $index,
                'id' => $influencerId,
                'username' => $username,
                'error' => 'Não atende ao filtro de seguidores',
                'followers' => $followers,
                'filter' => [
                    'min' => $minFollowers,
                    'max' => ($maxFollowers == PHP_INT_MAX) ? 'sem limite' : $maxFollowers
                ]
            ];
            
            $results['total_errors']++;
            continue;
        }
        
        // Adiciona os dados aos resultados
        $results['success'][] = [
            'index' => $index,
            'id' => $influencerId,
            'username' => $username,
            'followers' => $followers
        ];
        
        $results['data'][] = [
            'graphInfluencerId' => $influencerId,
            'graphInfluencerUserName' => $username,
            'apiData' => $businessDiscovery,
            'raw' => $responseData
        ];
        
        $results['total_success']++;
    }
    
    // Fecha o multi CURL
    curl_multi_close($mh);
    
    // Registra estatísticas finais
    error_log("Processamento concluído: {$results['total_success']} sucesso, {$results['total_errors']} erros");
    error_log("Filtro de seguidores aplicado: min={$minFollowers}, max=" . ($maxFollowers == PHP_INT_MAX ? "sem limite" : $maxFollowers));
    
    return $results;
}
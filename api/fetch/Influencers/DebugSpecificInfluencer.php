<?php
/**
 * Função de debug para testar filtros em um usuário específico
 * 
 * @param array $filterItems Filtros a serem testados
 * @param string $specificUserId ID específico do usuário a ser testado
 * @return array Resultados do teste com logs detalhados
 */
function debugSpecificUser($filterItems, $specificUserId) {
    error_log("=========== INICIANDO DEBUG PARA USUÁRIO ESPECÍFICO: $specificUserId ===========");
    require_once "../../conn/Wamp64Connection.php";
    $connToUsuarios = getWamp64Connection("Users");
    
    // Extrair os filtros
    $selectedNiches = $filterItems['selectedNiches'] ?? [];
    $selectedAges = $filterItems['selectedAges'] ?? [];
    $selectedCities = $filterItems['selectedCities'] ?? [];
    $selectedStates = $filterItems['selectedStates'] ?? [];
    $selectedContentTypes = $filterItems['selectedContentTypes'] ?? [];
    
    // Array para armazenar os resultados dos testes
    $testResults = [
        'userId' => $specificUserId,
        'userExists' => false,
        'userData' => null,
        'testResults' => [
            'tags' => ['passed' => false, 'details' => []],
            'age' => ['passed' => false, 'details' => []],
            'location' => ['passed' => false, 'details' => []]
        ],
        'filtersPassed' => false
    ];
    
    // 1. Verificar se o usuário existe e obter seus dados
    $queryUserData = "
        SELECT 
            graphInfluencerId,
            userTags,
            userLocation,
            userBirthdate
        FROM UserProfile 
        WHERE graphInfluencerId = ?
    ";
    
    $stmt = $connToUsuarios->prepare($queryUserData);
    $stmt->bind_param("s", $specificUserId);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $testResults['userExists'] = true;
            $testResults['userData'] = $row;
            
            // Extrair dados para facilitar os testes
            $userTags = json_decode($row['userTags'], true);
            $userLocation = json_decode($row['userLocation'], true);
            $userBirthdate = $row['userBirthdate'];
            
            error_log("Usuário encontrado. Dados:");
            error_log("Tags: " . print_r($userTags, true));
            error_log("Localização: " . print_r($userLocation, true));
            error_log("Data de Nascimento: $userBirthdate");
            
            // 2. Testar Tags/Nichos
            if (!empty($selectedNiches)) {
                $testResults['testResults']['tags']['details'] = [];
                $tagsMatched = false;
                
                foreach ($selectedNiches as $niche) {
                    $nicheLower = strtolower($niche);
                    $firstTagMatch = stripos($userTags['firstTag'] ?? '', $nicheLower) !== false;
                    $secondTagMatch = stripos($userTags['secondTag'] ?? '', $nicheLower) !== false;
                    $thirdTagMatch = stripos($userTags['thirdTag'] ?? '', $nicheLower) !== false;
                    
                    $testResults['testResults']['tags']['details'][$niche] = [
                        'firstTagMatch' => $firstTagMatch,
                        'secondTagMatch' => $secondTagMatch,
                        'thirdTagMatch' => $thirdTagMatch,
                        'anyMatch' => $firstTagMatch || $secondTagMatch || $thirdTagMatch
                    ];
                    
                    if ($firstTagMatch || $secondTagMatch || $thirdTagMatch) {
                        $tagsMatched = true;
                    }
                    
                    if ($firstTagMatch) {
                        error_log("Tag '$niche' encontrada na firstTag: " . ($userTags['firstTag'] ?? 'não definida'));
                    } else {
                        error_log("Tag '$niche' NÃO encontrada na firstTag: " . ($userTags['firstTag'] ?? 'não definida'));
                    }
                    
                    if ($secondTagMatch) {
                        error_log("Tag '$niche' encontrada na secondTag: " . ($userTags['secondTag'] ?? 'não definida'));
                    } else {
                        error_log("Tag '$niche' NÃO encontrada na secondTag: " . ($userTags['secondTag'] ?? 'não definida'));
                    }
                    
                    if ($thirdTagMatch) {
                        error_log("Tag '$niche' encontrada na thirdTag: " . ($userTags['thirdTag'] ?? 'não definida'));
                    } else {
                        error_log("Tag '$niche' NÃO encontrada na thirdTag: " . ($userTags['thirdTag'] ?? 'não definida'));
                    }
                }
                
                $testResults['testResults']['tags']['passed'] = $tagsMatched;
                error_log("Teste de tags " . ($tagsMatched ? "PASSOU" : "FALHOU"));
            } else {
                // Se não há nichos selecionados, considera como passou no teste
                $testResults['testResults']['tags']['passed'] = true;
                error_log("Nenhuma tag para testar - considerado como PASSOU");
            }
            
            // 3. Testar Idade
            if (!empty($selectedAges)) {
                $testResults['testResults']['age']['details'] = [];
                $ageMatched = false;
                $currentYear = date('Y');
                $birthYear = $userBirthdate ? date('Y', strtotime($userBirthdate)) : null;
                
                if ($birthYear) {
                    foreach ($selectedAges as $ageRange) {
                        if (preg_match('/(\d+)-(\d+)/', $ageRange, $matches)) {
                            $minAge = $matches[1];
                            $maxAge = $matches[2];
                            
                            $maxBirthYear = $currentYear - $minAge;
                            $minBirthYear = $currentYear - $maxAge;
                            
                            $inRange = ($birthYear >= $minBirthYear && $birthYear <= $maxBirthYear);
                            
                            $testResults['testResults']['age']['details'][$ageRange] = [
                                'minBirthYear' => $minBirthYear,
                                'maxBirthYear' => $maxBirthYear,
                                'userBirthYear' => $birthYear,
                                'inRange' => $inRange
                            ];
                            
                            if ($inRange) {
                                $ageMatched = true;
                                error_log("Idade na faixa '$ageRange': Ano de nascimento $birthYear está entre $minBirthYear e $maxBirthYear - PASSOU");
                            } else {
                                error_log("Idade FORA da faixa '$ageRange': Ano de nascimento $birthYear NÃO está entre $minBirthYear e $maxBirthYear");
                            }
                        }
                    }
                } else {
                    error_log("Data de nascimento inválida ou não definida: $userBirthdate");
                }
                
                $testResults['testResults']['age']['passed'] = $ageMatched;
                error_log("Teste de idade " . ($ageMatched ? "PASSOU" : "FALHOU"));
            } else {
                // Se não há idades selecionadas, considera como passou no teste
                $testResults['testResults']['age']['passed'] = true;
                error_log("Nenhuma faixa etária para testar - considerado como PASSOU");
            }
            
            // 4. Testar Localização (Cidade e Estado)
            $testResults['testResults']['location']['details'] = [
                'city' => ['passed' => false, 'details' => []],
                'state' => ['passed' => false, 'details' => []]
            ];
            
            // Testar cidade
            $cityMatched = false;
            if (!empty($selectedCities)) {
                $userCity = strtolower($userLocation['userCity'] ?? '');
                
                foreach ($selectedCities as $city) {
                    $cityLower = strtolower($city);
                    $match = ($userCity === $cityLower);
                    
                    $testResults['testResults']['location']['details']['city'][$city] = $match;
                    
                    if ($match) {
                        $cityMatched = true;
                        error_log("Cidade '$city' corresponde à cidade do usuário: $userCity - PASSOU");
                    } else {
                        error_log("Cidade '$city' NÃO corresponde à cidade do usuário: $userCity");
                    }
                }
                
                $testResults['testResults']['location']['details']['city']['passed'] = $cityMatched;
            } else {
                // Se não há cidades selecionadas, considera como passou no teste
                $cityMatched = true;
                $testResults['testResults']['location']['details']['city']['passed'] = true;
                error_log("Nenhuma cidade para testar - considerado como PASSOU");
            }
            
            // Testar estado
            $stateMatched = false;
            if (!empty($selectedStates)) {
                $userState = strtolower($userLocation['userState'] ?? '');
                
                foreach ($selectedStates as $stateAbbr) {
                    // Converter abreviatura para nome completo
                    $stateFull = convertStateAbbreviationToFullName($stateAbbr);
                    $stateFullLower = strtolower($stateFull ?? $stateAbbr);
                    
                    $match = ($userState === $stateFullLower);
                    
                    $testResults['testResults']['location']['details']['state'][$stateAbbr] = [
                        'abbr' => $stateAbbr,
                        'full' => $stateFull,
                        'userState' => $userState,
                        'match' => $match
                    ];
                    
                    if ($match) {
                        $stateMatched = true;
                        error_log("Estado '$stateAbbr' (convertido para '$stateFull') corresponde ao estado do usuário: $userState - PASSOU");
                    } else {
                        error_log("Estado '$stateAbbr' (convertido para '$stateFull') NÃO corresponde ao estado do usuário: $userState");
                    }
                }
                
                $testResults['testResults']['location']['details']['state']['passed'] = $stateMatched;
            } else {
                // Se não há estados selecionados, considera como passou no teste
                $stateMatched = true;
                $testResults['testResults']['location']['details']['state']['passed'] = true;
                error_log("Nenhum estado para testar - considerado como PASSOU");
            }
            
            $testResults['testResults']['location']['passed'] = $cityMatched && $stateMatched;
            error_log("Teste de localização " . (($cityMatched && $stateMatched) ? "PASSOU" : "FALHOU"));
            
            // 5. Verificar se todos os filtros passaram
            $allPassed = 
                $testResults['testResults']['tags']['passed'] && 
                $testResults['testResults']['age']['passed'] && 
                $testResults['testResults']['location']['passed'];
            
            $testResults['filtersPassed'] = $allPassed;
            error_log("RESULTADO FINAL: O usuário " . ($allPassed ? "ATENDE" : "NÃO ATENDE") . " a todos os critérios de filtro");
            
            // Verificar qual filtro está falhando
            if (!$allPassed) {
                if (!$testResults['testResults']['tags']['passed']) {
                    error_log("FALHA: Filtro de tags não foi atendido");
                }
                if (!$testResults['testResults']['age']['passed']) {
                    error_log("FALHA: Filtro de idade não foi atendido");
                }
                if (!$testResults['testResults']['location']['passed']) {
                    if (!$cityMatched) {
                        error_log("FALHA: Filtro de cidade não foi atendido");
                    }
                    if (!$stateMatched) {
                        error_log("FALHA: Filtro de estado não foi atendido");
                    }
                }
            }
        } else {
            error_log("ERRO: Usuário com ID $specificUserId não encontrado no banco de dados");
        }
    } else {
        error_log("ERRO ao executar consulta: " . $stmt->error);
    }
    
    $stmt->close();
    $connToUsuarios->close();
    
    error_log("=========== FIM DO DEBUG PARA USUÁRIO ESPECÍFICO ===========");
    
    return $testResults;
}
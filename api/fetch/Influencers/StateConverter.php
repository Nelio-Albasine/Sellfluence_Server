<?php
/**
 * Converte abreviaturas de estados para nomes completos
 * 
 * @param string $stateAbbreviation Abreviatura do estado (ex: "AL")
 * @return string|null Nome completo do estado ou null se não encontrado
 */
function convertStateAbbreviationToFullName($stateAbbreviation) {
    // Mapeamento de abreviaturas para nomes completos
    $statesMap = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    ];

    // Converter para maiúsculas para garantir a correspondência
    $stateAbbreviation = strtoupper($stateAbbreviation);
    
    // Verificar se a abreviatura existe no mapeamento
    if (isset($statesMap[$stateAbbreviation])) {
        return $statesMap[$stateAbbreviation];
    }
    
    // Retornar null se a abreviatura não for encontrada
    return null;
}
<?php

if ($isInfluencer) {
    insertNewInfluencer($userId, $influencerId, $influencerUserName, $current);
} else {
    insertNewCompany($userId, "Empresa Nelio Inc", $current);
}

function insertNewInfluencer(
    $userId,
    $influencerId,
    $influencerUserName,
    $current
) {}

function insertNewCompany(
    $userId,
    $companyName,
    $current
) {}

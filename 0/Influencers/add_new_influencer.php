<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');


try {
    require '../connection.php';
    $conn = getDatabaseConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    $influencerLoginId = $data['influencerLoginId'] ?? null;
    $influencerAccessToken = $data['influencerAccessToken'] ?? null;
    $influencerTokenCreationDate = $data['influencerTokenCreationDate'] ?? null;
    $influencerTokenExpirationDate = $data['influencerTokenExpirationDate'] ?? null;
    $influencerName = $data['influencerName'] ?? null;
    $influencerUserName = $data['influencerUserName'] ?? null;
    $influencerPicURL = $data['influencerPicURL'] ?? null;
    $influencerInstagramDescription = $data['influencerInstagramDescription'] ?? null;
    $influencerFollowers = $data['influencerFollowers'] ?? null;


    $conn->close();
} catch (\Throwable $th) {
    error_log('Selfluence "TAG(add_new_influencer.php)": Error occurred: ' . $th->getMessage());
}
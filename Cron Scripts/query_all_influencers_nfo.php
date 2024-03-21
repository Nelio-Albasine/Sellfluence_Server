<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');


try {
    require '../connection.php';
    $conn = getDatabaseConnection();

    //Lets use the influencerAccessToken as the Key to query each influencer info
    # Howover, when returning these info to the client side or even makink the request, 
    #... We gonna use the influencerLoginId as the ID, in order to protect each influencers AccessToken
    $createInfluencersTB = "CREATE TABLE IF NOT EXISTS Influencers_Public (
        id INT AUTO_INCREMENT PRIMARY KEY,
        influencerLoginId VARCHAR(255) NOT NULL UNIQUE,
        influencerAccessToken VARCHAR(255),
        influencerTokenCreationDate DATETIME,
        influencerTokenExpirationDate DATETIME,
        influencerName VARCHAR(255),
        influencerUserName VARCHAR(255),
        influencerPicURL VARCHAR(255),
        influencerInstagramDescription VARCHAR(255),
        influencerFollowers INT


    )";
    $createInfluencersTB = "CREATE TABLE IF NOT EXISTS Influencers_Metadados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        influencerLoginId VARCHAR(255) NOT NULL UNIQUE,
        influencerAccessToken VARCHAR(255),
        influencerTokenCreationDate DATETIME,
        influencerTokenExpirationDate DATETIME,
        influencerName VARCHAR(255),
        influencerUserName VARCHAR(255),
        influencerPicURL VARCHAR(255),
        influencerInstagramDescription VARCHAR(255),
        influencerFollowers INT


    )";



    $conn->query($createInfluencersTB);
    




    $conn->close();
} catch (\Throwable $th) {
    error_log('Selfluence "TAG(query_all_influencers.php)": Error occurred: ' . $th->getMessage());
}
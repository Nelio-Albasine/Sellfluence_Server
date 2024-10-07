<?php
function getFromUserProfile($chatOwner): array
{
    $response = [
        "userProfilePicURL" => null,
        "userMutableName" => null
    ];

    $filePath = "./Wamp64Connection.php";
    if (!file_exists($filePath)) {
        die("Erro: O arquivo $filePath não foi encontrado no Include Fro m profile.");
    } 

    include_once "./Wamp64Connection.php";

    $conn = getMySQLConnection("users");

    if ($conn === false) {
        return ["error" => "Falha na conexão com o banco de dados"];
    }

    $query = "SELECT userMutableName, userProfilePicURL FROM UserProfile WHERE userId = ? OR graphInfluencerId = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ss", $chatOwner, $chatOwner);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $response = $result->fetch_assoc();
        }

        $stmt->close();
    } else {
        return ["error" => "Falha ao preparar a consulta SQL"];
    }

    $conn->close();

    return $response;
}


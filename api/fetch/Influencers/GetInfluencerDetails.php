<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/error_GetInfluencerDetails.log');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    $influencerId = $_GET["influencerId"];
    $reviews = getInfluencerReview($influencerId);
    echo json_encode($reviews);
} else {
    error_log("The request method is not GET");
}

function getInfluencerReview($influencerId) {
    require_once "../Wamp64Connection.php";
    $connReviews = getWamp64Connection("userAccType");
    $connUserProfile = getWamp64Connection("users");

    $reviews = [];

    $query = "SELECT * FROM InfluencerReview WHERE influencerId = ?";
    $stmt = $connReviews->prepare($query);

    if ($stmt) {
        $stmt->bind_param("s", $influencerId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $review = $row;
                $reviewerId = $row['reviewerId'];

                // Verifica se o reviewerId está na tabela userProfile
                $profileQuery = "SELECT userId, graphInfluencerId, userProfilePicURL, userMutableName FROM userProfile WHERE userId = ? OR graphInfluencerId = ?";
                $profileStmt = $connUserProfile->prepare($profileQuery);

                if ($profileStmt) {
                    $profileStmt->bind_param("ss", $reviewerId, $reviewerId);
                    $profileStmt->execute();
                    $profileResult = $profileStmt->get_result();

                    if ($profileResult->num_rows > 0) {
                        $profileData = $profileResult->fetch_assoc();
                        
                        // Determina se o review foi feito por uma empresa ou influenciador
                        if ($profileData['userId'] == $reviewerId) {
                            $review['reviewerType'] = "Empresa";
                        } else if ($profileData['graphInfluencerId'] == $reviewerId) {
                            $review['reviewerType'] = "Influenciador";
                        }

                        // Adiciona as informações do perfil ao review
                        $review['userProfilePicURL'] = $profileData['userProfilePicURL'];
                        $review['userMutableName'] = $profileData['userMutableName'];
                    }

                    $profileStmt->close();
                }

                $reviews[] = $review;
            }
        }

        $stmt->close();
    } else {
        echo "Erro ao preparar a declaração: " . $connReviews->error;
        return false;
    }

    $connReviews->close();
    $connUserProfile->close();

    return $reviews;
}

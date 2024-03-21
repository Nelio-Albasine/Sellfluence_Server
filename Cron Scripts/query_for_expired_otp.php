<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require 'connection.php';
    $conn = getDatabaseConnection();

    $currentTime = date('Y-m-d H:i:s'); 
    $sql = "UPDATE AUTHENTICATION_OTP SET OTP_EXPIRED = TRUE WHERE OTP_EXPIRATION < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $currentTime);
    $result = $stmt->execute();

    if ($result) {
        echo json_encode(["success" => true, "message" => "Expired OTPs marked as expired."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update expired OTPs."]);
    }

    $stmt->close();
    $conn->close();
} catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "An error occurred on the server."]);
}
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

try {
    require 'connection.php';
    $conn = getDatabaseConnection();
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['submitedOTP']) && isset($data['userId'])) {
        $user_uid = $data['userId'];
        $submittedOtp = $data['submitedOTP'];

        function verifyOTP($userId, $submittedOtp, $conn)
        {
            $currentTime = date('Y-m-d H:i:s');
            $sql = "SELECT OTP, OTP_EXPIRED FROM AUTHENTICATION_OTP WHERE userId = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                #Code Status
                # STATUS: 22 => The OTP is valid
                # STATUS: 44 => The OTP is expired
                # STATUS: 88 => The OTP is invalid
                # STATUS: 0 => No OTP found for this user.

                if ($row['OTP'] == $submittedOtp && $currentTime <= $row['OTP_EXPIRATION']) {
                    echo json_encode(["isValid_OTP" => true, "STATUS" => "22"]);
                } else if ($row['OTP_EXPIRED']) {
                    echo json_encode(["isValid_OTP" => false, "STATUS" => "44"]);
                } else {
                    echo json_encode(["isValid_OTP" => false, "STATUS" => "88"]);
                }
            } else {
                echo json_encode(["isValid_OTP" => false, "STATUS" => "0"]);
                echo "";
            }
            $stmt->close();
        }
    }



    $conn->close();
} catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro no servidor."]);
}

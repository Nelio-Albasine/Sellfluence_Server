<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');


try {
    require 'connection.php';
    $conn = getDatabaseConnection();
    $data = json_decode(file_get_contents("php://input"), true);

    $createInfluencersTB = "CREATE TABLE IF NOT EXISTS AUTHENTICATION_OTP (
        userId VARCHAR(255) PRIMARY KEY,
        OTP INT,
        OTP_EXPIRED BOOLEAN,
        OTP_EXPIRATION VARCHAR(255)
    )";

    if (isset($data['userId']) && isset($data['email'])) {
        $user_uid = $data['userId'];
        $user_email = $data['email'];
        function generateAndStoreOTP($param_userId, $param_connection)
        {
            $otp = rand(100000, 999999); // Generate a 6-digit OTP, like: 070702
            $expiryTime = date('Y-m-d H:i:s', strtotime('+5 minutes')); // OTP expires in 5 minutes
            $otpExpired = false;
            $sql = "INSERT INTO AUTHENTICATION_OTP (userId, OTP, OTP_EXPIRED, OTP_EXPIRATION) VALUES (?, ?, ?, ?)";
            $stmt = $param_connection->prepare($sql);
            $stmt->bind_param("sis", $param_userId, $otp, $expiryTime, $otpExpired);
            $stmt->execute();

            sendOTPToUser($otp);
            $stmt->close();
        }
        generateAndStoreOTP($user_uid, $conn);

        function sendOTPToUser($otp_generated)
        {
            require("/home/parceria/sellfluence.com.br/PHPMailer-master/src/PHPMailer.php");
            require("/home/parceria/sellfluence.com.br/PHPMailer-master/src/SMTP.php");
            require("/home/parceria/sellfluence.com.br/PHPMailer-master/src/Exception.php");
        

            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'ssl';
            $mail->Host = "mail.sellfluence.com.br";
            $mail->Port = 465; // or 587 
            $mail->IsHTML(true);
            $mail->Username = "noreply@sellfluence.com.br";
            $mail->Password = "alfa1vsomega2M@";
            $mail->SetFrom("noreply@sellfluence.com.br", "Welcome to Sellfluence");
            $mail->CharSet = 'UTF-8';
            $mail->Subject = "Código de verificação";

         
            $mail->Subject = "Código de verificação";
                $mail->Body = '
                <html>
                <head>
                <title>Código de Verificação</title>
                <style>
                body {font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;}
                .container {background-color: #ffffff; margin: 20px auto; padding: 20px; max-width: 600px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);}
                .header {color: #333333; margin-bottom: 20px; text-align: center;}
                .content {color: #666666; text-align: center;}
                .otp {margin: 20px 0; padding: 10px;}
                .otp div {display: inline-block; margin: 0 5px; padding: 10px; background-color: #007bff; color: #ffffff; border-radius: 5px; width: 30px; height: 30px; line-height: 30px; font-size: 20px; font-weight: bold;}
                .footer {font-size: 12px; color: #999999; text-align: center; margin-top: 20px;}
                </style>
                </head>
                <body>
                <div class="container">
                <div class="header">
                    <h2>Código de Verificação</h2>
                </div>
                <div class="content">
                    <p>Olá! Use o código abaixo para confirmar seu endereço de email:</p>
                    <div class="otp">';
                    
                foreach (str_split($otp_generated) as $char) {
                    $mail->Body .= '<div>' . $char . '</div>';
                }
                
                $mail->Body .= '
                    </div>
                    <p>O código é válido por 5 minutos. Por favor, não compartilhe este código com ninguém.</p>
                </div>
                <div class="footer">
                    <p>Se você não solicitou este email, por favor ignore-o.</p>
                </div>
                </div>
                </body>
                </html>
                ';
                

                
            $mail->AddAddress($user_email);
            try {
                $mail->send();
                echo json_encode(['emailSent' => true, 'message' => 'Código enviado!']);
            } catch (Exception $e) {
                error_log("Erro ao enviar email: " . $mail->ErrorInfo);
                echo json_encode(['emailSent' => true, 'message' => 'Erro ao enviar o email.']);
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "Alguns campos recebidos estão vazios!"]);
    }

    $conn->close();
} catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro no servidor."]);
}

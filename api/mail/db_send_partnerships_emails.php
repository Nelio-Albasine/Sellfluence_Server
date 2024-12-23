<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');


try {
    require 'connection.php';
    $conn = getDatabaseConnection();
    $data = json_decode(file_get_contents("php://input"), true);


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
        $mail->SetFrom("noreply@sellfluence.com.br", "Sellfluence");
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Parab�ns pela parceria concluida!";

        $mail->Body = '
        <!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parabéns pela Sua Primeira Venda!</title>
<style>
  body {font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; padding: 0; margin: 0; background-color: #f0f2f5;}
  .email-container {max-width: 600px; margin: auto; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
  .header {background-color: #007bff; color: #ffffff; padding: 20px; text-align: center; font-size: 24px;}
  .content {padding: 20px; line-height: 1.6;}
  .sale-highlight {background-color: #eaf1f8; padding: 15px; margin: 20px 0; border-left: 5px solid #007bff; font-size: 16px;}
  .footer {background-color: #f0f2f5; color: #6c757d; text-align: center; padding: 10px; font-size: 14px;}
  .button {background-color: #28a745; color: #ffffff; padding: 8px 20px; margin: 20px auto; display: block; width: fit-content; border-radius: 5px; text-decoration: none;}
</style>
</head>
<body>
<div class="email-container">
  <div class="header">
    Sua Primeira Perceria 🎉
  </div>
  <div class="content">
    <p>Olá <strong>[Nome do Usuario]</strong>!</p>
    <p>Estamos emocionados em compartilhar que você realizou sua <strong>primeira venda</strong> em nosso aplicativo! Isso é apenas o começo de sua jornada de sucesso.</p>
    <div class="sale-highlight">
      <p><strong>Detalhes da Venda:</strong></p>
      <p>Produto: <strong>[Nome do Produto]</strong></p>
      <p>Valor: <strong>R$[Valor]</strong></p>
      <p>Data: <strong>[Data]</strong></p>
      <p>Transação: <strong>[ID]</strong></p>
    </div>
    <p>Queremos garantir que sua experiência conosco seja a melhor possível. Sua satisfação e sucesso são nossas prioridades máximas.</p>
    <a href="[Link Para more Detalhes]" class="button">Veja Mais Detalhes</a>
  </div>
  <div class="footer">
    Precisa de ajuda? <a href="mailto:sellfluence.com.br">Entre em contato</a> com nosso suporte.
  </div>
</div>
</body>
</html>

        ';



        $mail->AddAddress('maycond90@gmail.com');
        try {
            $mail->send();
            echo json_encode(['emailSent' => true, 'message' => 'Código enviado!']);
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $mail->ErrorInfo);
            echo json_encode(['emailSent' => true, 'message' => 'Erro ao enviar o email.']);
        }
    $conn->close();
} catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro no servidor."]);
}

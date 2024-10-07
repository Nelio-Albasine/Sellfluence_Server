<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function sendProposalEmailToReceiver(
  $user_name, 
  $user_email, 
  $amount, 
  $data, 
  $transactionId) {
  try {
 
    require ("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/PHPMailer.php");
    require ("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/SMTP.php");
    require ("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/Exception.php");

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
    $mail->Subject = "Nova proposta de parceria!";

    $mail->Body = '
                <!DOCTYPE html>
        <html lang="pt">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Proposta de parceria disponível!</title>
        <style>
          body {font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; padding: 0; margin: 0; background-color: #f0f2f5;}
          .email-container {max-width: 600px; margin: auto; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
          .header {background-color: #007bff; color: #ffffff; padding: 30px 20px; text-align: center; font-size: 24px;}
          .content {padding: 20px; line-height: 1.6;}
          .sale-highlight {background-color: #eaf1f8; padding: 15px; margin: 20px 0; border-left: 5px solid #007bff; font-size: 16px;}
          .footer {background-color: #f0f2f5; color: #6c757d; text-align: center; padding: 10px; font-size: 14px;}
          .button {background-color: #28a745; color: #ffffff; padding: 8px 20px; margin: 20px auto; display: block; width: fit-content; border-radius: 5px; text-decoration: none;}
        </style>
        </head>
        <body>
        <div class="email-container">
          <div class="header">
            PROPOSTA DE PARCERIA!
          </div>
          <div class="content">
            <h2>Olá, <strong>'.$user_name.'</strong>!</h2>
            <h3>Parabéns! Você acabou de receber uma proposta de parceria!</h3>
            <div class="sale-highlight">
              <p><strong>DETALHES DA PROPOSTA:</strong></p>
              <p>Valor: <strong>'.$amount.'</strong></p>
              <p>Data: <strong>'.$data.'</strong></p>
              <p>ID da transaÃ§Ã£o: <strong>'.$transactionId.'</strong></p>
            </div>
            <a href="https://play.google.com/store/apps/details?id=com.find.influencers" class="button">Veja Mais Detalhes</a>
            <p>Queremos garantir que sua experiÃªncia conosco seja a melhor possÃ­vel. Sua satisfaÃ§Ã£o e sucesso sÃ£o nossas prioridades mÃ¡ximas.</p>
            </div>
          <div class="footer">
            Precisa de ajuda? <a href="mailto:sellfluence.com.br">Entre em contato</a> com nosso suporte.
          </div>
        </div>
        </body>
        </html>
                ';


    $mail->AddAddress($user_email);
    try {
      $mail->send();
      return true;
    } catch (Exception $e) {
      error_log("Erro ao enviar email: " . $mail->ErrorInfo);
      echo json_encode(['emailSent' => true, 'message' => 'Erro ao enviar o email.']);
      return false;
    }
    
  } catch (\Throwable $th) {
    error_log('Error occurred: ' . $th->getMessage());
    echo json_encode(["success" => false, "message" => "Ocorreu um erro no servidor."] .$th->getMessage());
  }
}



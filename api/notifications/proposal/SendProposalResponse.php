<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log',  __DIR__ . '/sendProposalResponseErrors.log');
header('Content-Type: application/json; charset=utf-8');

//require_once __DIR__ . '../../../vendor/autoload.php';
require '/home/parceria/sellfluence.com.br/vendor/autoload.php';

use GuzzleHttp\Client;
use Google_Client;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        logRequest($data);

        $resquireFields = ["to", "proposalResponse", "chatId", "messageId", "proposalTotalPrice"];
        $missingFields = array_filter($resquireFields, function ($field) use ($data) {
            return empty($data[$field]);
        });


        if ($missingFields) {
            logMessage("Missing fields: " . implode('.', $missingFields));
            echo json_encode(["message" => "Campos obriigatorios faltando: " . implode('.', $missingFields)]);
            exit;
        }

        //primary data
        $to = $data["to"];
        $chatId = $data["chatId"];
        $messageId = $data["messageId"];
        $companyNotificationToken = $data["companyNotificationToken"];

        $proposalResponseJson = json_decode($data["proposalResponse"], true);
        $proposalResponseStatus = (int) $proposalResponseJson["proposalResponseStatus"];

        $participantInfo = json_decode($data["participantsInfo"], true);


        $locale = 'pt_BR';
        $dateType = IntlDateFormatter::LONG;
        $dateFormatter = new IntlDateFormatter($locale, $dateType, IntlDateFormatter::NONE);
        $currentDate = new DateTime();
        $formattedDate = $dateFormatter->format($currentDate);

        $info = [
            "to" => $to,
            "proposalResponseStatus" => $proposalResponseStatus,
            "companyNotificationToken" => $companyNotificationToken,
            "companyName" => $participantInfo["companyName"],
            "influencerName" => $participantInfo["influencerName"],
            "companyProfilePic" => $participantInfo["companyProfilePic"],
            "influencerProfilePic" => $participantInfo["influencerProfilePic"],
            "proposalId" => $messageId,
            "proposalTotalPrice" => $data["proposalTotalPrice"],
            "date" => $formattedDate,
            "chatId" => $chatId
        ];

        $finalResponse = false;

        switch ($proposalResponseStatus) {
            case 0:
                //proposal accepted
                $finalResponse = SendProposalApprovalEmail($info);
                break;
            case 1:
                //Discuss proposal
                $ReasonforDiscuss = $proposalResponseJson["Reason"];
                $finalResponse = SendProposalDiscussionEmail($ReasonforDiscuss, $info);
                break;
            case 2:
                //proposal denied
                $ReasonforRefusal = $proposalResponseJson["Reason"];
                $finalResponse = sendReasonForRefusingTheProposal($ReasonforRefusal, $info);
                break;
        }

        if ($finalResponse) {
            $finalResponse = notifyCompanyDevice(
                $info,
                $proposalResponseJson["Reason"],
            );
        }

        echo json_encode(["success" => $finalResponse]);
    } catch (\Throwable $th) {
        error_log("Erro ao enviar o email: " . $th->getMessage());
    }
} else {
    error_log("The request method is not POST");
}

function logRequest($data)
{
    $logData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'body' => file_get_contents('php://input')
    ];
    $logContent = json_encode($logData, JSON_PRETTY_PRINT);
    logMessage("Requisição recebida:\n" . $logContent);
}

function logMessage($message)
{
    error_log($message, 3, __DIR__ . '/sendProposalResponseErrors.log');
}

function SendProposalApprovalEmail($info)
{
    $to = $info["to"];
    $companyName = $info["companyName"];
    $influencerName = $info["influencerName"];
    $companyProfilePic = $info["companyProfilePic"];
    $influencerProfilePic = $info["influencerProfilePic"];
    $proposalId = $info["proposalId"];
    $proposalTotalPrice = $info["proposalTotalPrice"];
    $proposalApprovaralDate = $info["date"];
    $chatId = $info["chatId"];

    try {
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/PHPMailer.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/SMTP.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/Exception.php");

        /* require ("../../../PHPMailer-master/PHPMailer-master/src/PHPMailer.php");
        require ("../../../PHPMailer-master/PHPMailer-master/src/SMTP.php");
        require ("../../../PHPMailer-master/PHPMailer-master/src/Exception.php"); */

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
        $mail->Subject = "Boas notícias: Proposta Aprovada!";

        $mail->Body = '
        <!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Proposta Aprovada - Sellfluence</title>
                    <style>
                        body {
                            font-family: \'Roboto\', sans-serif;
                            background-color: #f0f4f8;
                            margin: 0;
                            padding: 0;
                            color: #333;
                        }
                        .email-container {
                            max-width: 600px;
                            margin: 0 auto;
                            background-color: #ffffff;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                        }
                        .email-header {
                            background-color: #4CAF50; 
                            color: white;
                            text-align: center;
                            padding: 30px 20px;
                            font-size: 24px;
                            font-weight: bold;
                        }
                        .email-body {
                            padding: 20px;
                            color: #333;
                            text-align: center;
                        }
                        .email-body h2 {
                            font-size: 20px;
                            margin-bottom: 10px;
                        }
                        .profile-section {
                            position: relative;
                            margin: 20px auto;
                            width: 100px; 
                            height: 100px; 
                            display: flex;
                            align-self: center;
                            margin: 0 auto;
                            align-items: center;
                            justify-content: center;
                        }
                        .profile-section img {
                            width: 80px;
                            height: 80px;
                            border-radius: 50%;
                            border: 5px solid #4CAF50; 
                            position: absolute;
                        }
                        .profile-influencer {
                            left: 25px; 
                        }
                        .profile-company {
                            right: 25px;
                        }
                        .connect-symbol {
                            font-size: 28px;
                            color: #4CAF50;
                            position: relative;
                            z-index: 1;
                        }
                        .proposal-info {
                            background-color: #f8f8f8;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 20px 0;
                            border-left: 5px solid #4CAF50;
                            text-align: left;
                        }
                        .proposal-info h4 {
                            margin-bottom: 5px;
                            font-size: 14px;
                            color: #333;
                        }
                        .proposal-info p {
                            margin: 0;
                            font-size: 12px;
                            color: #555;
                        }
                        .cta-button {
                            background-color: #4CAF50;
                            color: white;
                            text-decoration: none;
                            padding: 10px 20px;
                            border-radius: 6px;
                            display: inline-block;
                            margin-top: 15px;
                            text-transform: uppercase;
                            font-size: 12px;
                        }
                        .cta-button:hover {
                            background-color: #388E3C; 
                        }
                        .chat-info {
                            margin: 20px 0;
                            text-align: center;
                        }
                        .chat-info h3 {
                            margin-bottom: 5px;
                            font-size: 16px;
                            color: #4CAF50;
                        }
                        .email-footer {
                            text-align: center;
                            padding: 15px;
                            font-size: 11px;
                            color: #999;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="email-header">
                            Sellfluence - Proposta Aprovada!
                        </div>

                        <div class="email-body">
                            <h2>Parabéns, <span id="companyName">' . $companyName . '!</span></h2>
                            <p>Sua proposta foi <strong>aprovada</strong> por <strong id="influencerName">' . $influencerName . '</strong>: Estamos animados para começar esta parceria!</p>

                            <div class="profile-section">
                                <img id="influencerProfilePic" src="' . $influencerProfilePic . '" alt="Perfil do Influenciador" class="profile-influencer">
                                <img id="companyProfilePic" src="' . $companyProfilePic . '" alt="Perfil da Empresa" class="profile-company">
                            </div>

                            <div class="proposal-info">
                                <h4>ID da Proposta:</h4>
                                <p><strong>' . $proposalId . '</strong></p>

                                <h4>Preço:</h4>
                                <p><strong>' . $proposalTotalPrice . '</strong></p>

                                <h4>Data de aprovação:</h4>
                                <p><strong>' . $proposalApprovaralDate . '</strong></p>
                            </div>

                            <div class="chat-info">
                                <h3>Inicie a Conversa!</h3>
                                <p><strong>Agora você pode conversar diretamente com o influenciador pelo chat!</strong></p>
                                <a href="[Link para o Chat]" class="cta-button">Acessar Chat</a>
                            </div>
                        </div>

                        <div class="email-footer">
                            <p>Este é um e-mail automático, por favor, não responda a esta mensagem.</p>
                            <p>© 2024 Sellfluence - Todos os direitos reservados</p>
                        </div>
                    </div>
                </body>
                </html>
        ';

        $mail->AddAddress($to);
        $mail->send();
        return true;
    } catch (\Throwable $th) {
        error_log('Error occurred sending ProposalApprovalEmail: ' . $th->getMessage());
        return false;
    }
}

function SendProposalDiscussionEmail($reason, $info)
{
    $to = $info["to"];
    $companyName = $info["companyName"];
    $influencerName = $info["influencerName"];
    $companyProfilePic = $info["companyProfilePic"];
    $influencerProfilePic = $info["influencerProfilePic"];
    $proposalId = $info["proposalId"];
    $proposalTotalPrice = $info["proposalTotalPrice"];
    $proposalDiscussDate = $info["date"];
    $discussReason = $reason;
    $chatId = $info["chatId"];


    try {
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/PHPMailer.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/SMTP.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/Exception.php");

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
        $mail->Subject = "Debater proposta!";

        $mail->Body = '
                <!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Debater proposta - Sellfluence</title>
                    <style>
                        body {
                            font-family: \'Roboto\', sans-serif;
                            background-color: #f0f4f8;
                            margin: 0;
                            padding: 0;
                            color: #333;
                        }
                        .email-container {
                            max-width: 600px;
                            margin: 0 auto;
                            background-color: #ffffff;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                        }
                        .email-header {
                            background-color: #FF9800; 
                            color: white;
                            text-align: center;
                            padding: 30px 20px;
                            font-size: 24px;
                            font-weight: bold;
                        }
                        .email-body {
                            padding: 20px;
                            color: #333;
                            text-align: center;
                        }
                        .email-body h2 {
                            font-size: 20px;
                            margin-bottom: 10px;
                        }
                        .profile-section {
                            position: relative;
                            margin: 20px auto;
                            width: 100px; 
                            height: 100px; 
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .profile-section img {
                            width: 80px;
                            height: 80px;
                            border-radius: 50%;
                            border: 5px solid #FF9800;
                            position: absolute;
                        }
                        .profile-influencer {
                            left: 25px; 
                        }
                        .profile-company {
                            right: 25px;
                        }
                        .connect-symbol {
                            font-size: 28px;
                            color: #FF9800;
                            position: relative;
                            z-index: 1;
                        }
                        .proposal-info {
                            background-color: #f8f8f8;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 20px 0;
                            border-left: 5px solid #FF9800;
                            text-align: left;
                        }
                        .proposal-info h4 {
                            margin-bottom: 5px;
                            font-size: 14px;
                            color: #333;
                        }
                        .proposal-info p {
                            margin: 0;
                            font-size: 12px;
                            color: #555;
                        }
                        .discussion-reason {
                            background-color: #fffbeb;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 20px 0;
                            text-align: left;
                            border-left: 5px solid #FF9800;
                        }
                        .discussion-reason h4 {
                            font-size: 16px;
                            color: #FF9800;
                            margin-bottom: 10px;
                        }
                        .discussion-reason p {
                            margin: 0;
                            font-size: 14px;
                            color: #333;
                        }
                        .cta-button {
                            background-color: #FF9800;
                            color: white;
                            text-decoration: none;
                            padding: 10px 20px;
                            border-radius: 6px;
                            display: inline-block;
                            margin-top: 15px;
                            text-transform: uppercase;
                            font-size: 12px;
                        }
                        .cta-button:hover {
                            background-color: #F57C00; 
                        }
                        .chat-info {
                            margin: 20px 0;
                            text-align: center;
                        }
                        .chat-info h3 {
                            margin-bottom: 5px;
                            font-size: 16px;
                            color: #FF9800;
                        }
                        .email-footer {
                            text-align: center;
                            padding: 15px;
                            font-size: 11px;
                            color: #999;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="email-header">
                            Sellfluence - Proposta em Discussão
                        </div>

                        <div class="email-body">
                            <h2>Olá, <span id="companyName">' . $companyName . '</span></h2>
                            <p>A sua proposta foi recebida por <strong id="influencerName">' . $influencerName . '</strong>, porém ela gostaria de discutir alguns pontos antes de tomar uma decisão.</p>

                            <div class="profile-section">
                                <img id="influencerProfilePic" src="' . $influencerProfilePic . '" alt="Perfil do Influenciador" class="profile-influencer">
                                <img id="companyProfilePic" src="' . $companyProfilePic . '" alt="Perfil da Empresa" class="profile-company">
                            </div>

                            <div class="proposal-info">
                                <h4>ID da Proposta:</h4>
                                <p><strong>' . $proposalId . '</strong></p>

                                <h4>Preço Original:</h4>
                                <p><strong>' . $proposalTotalPrice . '</strong></p>

                                <h4>Data do Envio:</h4>
                                <p><strong>' . $proposalDiscussDate . '</strong></p>
                            </div>

                            <div class="discussion-reason">
                                <h4>Motivos para Discussão:</h4>
                                <p id="discussionPoints">' . $discussReason . '</p>
                            </div>

                            <div class="chat-info">
                                <h3>Vamos Conversar!</h3>
                                <p><strong>Você pode usar o chat para discutir diretamente com o influenciador sobre os ajustes na proposta.</strong></p>
                                <a href="[Link para o Chat]" class="cta-button">Acessar Chat</a>
                            </div>
                        </div>

                        <div class="email-footer">
                            <p>Este é um e-mail automático, por favor, não responda a esta mensagem.</p>
                            <p>© 2024 Sellfluence - Todos os direitos reservados</p>
                        </div>
                    </div>
                </body>
                </html>
        ';

        $mail->AddAddress($to);
        $mail->send();
        return true;
    } catch (\Throwable $th) {
        error_log('Error occurred sending ProposalApprovalEmail: ' . $th->getMessage());
        return false;
    }
}

function sendReasonForRefusingTheProposal($reason, $info)
{
    $to = $info["to"];
    $companyName = $info["companyName"];
    $influencerName = $info["influencerName"];
    $companyProfilePic = $info["companyProfilePic"];
    $influencerProfilePic = $info["influencerProfilePic"];
    $proposalId = $info["proposalId"];
    $proposalTotalPrice = $info["proposalTotalPrice"];
    $proposalReprovalDate = $info["date"];
    $repprovalReason = $reason;
    $chatId = $info["chatId"];

    try {
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/PHPMailer.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/SMTP.php");
        require("/home/parceria/sellfluence.com.br/PHPMailer-master/PHPMailer-master/src/Exception.php");

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
        $mail->Subject = "Proposta Recusada!";

        $mail->Body = '
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Proposta Recusada - Sellfluence</title>
                <style>
                    body {
                        font-family: \'Roboto\', sans-serif;
                        background-color: #f0f4f8;
                        margin: 0;
                        padding: 0;
                        color: #333;
                    }
                    .email-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                    }
                    .email-header {
                        background-color: #FF5252; /* Cor de recusa */
                        color: white;
                        text-align: center;
                        padding: 30px 20px;
                        font-size: 24px;
                        font-weight: bold;
                    }
                    .email-body {
                        padding: 20px;
                        color: #333;
                        text-align: center;
                    }
                    .email-body h2 {
                        font-size: 20px;
                        margin-bottom: 10px;
                    }
                    .profile-section {
                        position: relative;
                        margin: 20px auto;
                        width: 100px; 
                        height: 100px; 
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .profile-section img {
                        width: 80px;
                        height: 80px;
                        border-radius: 50%;
                        border: 5px solid #FF5252; 
                        position: absolute;
                    }
                    .profile-influencer {
                        left: 25px; 
                    }
                    .profile-company {
                        right: 25px;
                    }
                    .connect-symbol {
                        font-size: 28px;
                        color: #FF5252;
                        position: relative;
                        z-index: 1;
                    }
                    .proposal-info {
                        background-color: #f8f8f8;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 20px 0;
                        border-left: 5px solid #FF5252;
                        text-align: left;
                    }
                    .proposal-info h4 {
                        margin-bottom: 5px;
                        font-size: 14px;
                        color: #333;
                    }
                    .proposal-info p {
                        margin: 0;
                        font-size: 12px;
                        color: #555;
                    }
                    .reason-section {
                        background-color: #ffeded;
                        padding: 15px;
                        border-radius: 8px;
                        border-left: 5px solid #FF5252;
                        margin-top: 20px;
                        text-align: left;
                    }
                    .reason-section h4 {
                        margin-bottom: 5px;
                        font-size: 14px;
                        color: #FF5252;
                    }
                    .reason-section p {
                        margin: 0;
                        font-size: 12px;
                        color: #333;
                    }
                    .cta-button {
                        background-color: #FF5252;
                        color: white;
                        text-decoration: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        display: inline-block;
                        margin-top: 15px;
                        text-transform: uppercase;
                        font-size: 12px;
                    }
                    .cta-button:hover {
                        background-color: #D32F2F; 
                    }
                    .email-footer {
                        text-align: center;
                        padding: 15px;
                        font-size: 11px;
                        color: #999;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        Sellfluence - Proposta Recusada
                    </div>

                    <div class="email-body">
                        <h2>Olá, <span id="companyName">' . $companyName . '</span></h2>
                        <p>Infelizmente, a sua proposta foi <strong>recusada</strong> pelo influenciador <strong id="influencerName">' . $influencerName . '</strong>.</p>

                        <div class="profile-section">
                            <img id="influencerProfilePic" src="' . $influencerProfilePic . '" alt="Perfil do Influenciador" class="profile-influencer">
                            <img id="companyProfilePic" src="' . $companyProfilePic . '" alt="Perfil da Empresa" class="profile-company">
                        </div>

                        <div class="proposal-info">
                            <h4>ID da Proposta:</h4>
                            <p><strong>' . $proposalId . '</strong></p>

                            <h4>Preço Proposto:</h4>
                            <p><strong>' . $proposalTotalPrice . '</strong></p>

                            <h4>Data de Recusa:</h4>
                            <p><strong>' . $proposalReprovalDate . '</strong></p>
                        </div>

                        <div class="reason-section">
                            <h4>Motivo da Recusa:</h4>
                            <p id="rejectionReason"><strong>' . $repprovalReason . '</strong></p>
                        </div>

                        <a href="[Link para discutir nova proposta]" class="cta-button">Rever Proposta</a>
                    </div>

                    <div class="email-footer">
                        <p>Este é um e-mail automático, por favor, não responda a esta mensagem.</p>
                        <p>© 2024 Sellfluence - Todos os direitos reservados</p>
                    </div>
                </div>
            </body>
            </html>
        ';

        $mail->AddAddress($to);
        $mail->send();
        return true;
    } catch (\Throwable $th) {
        error_log('Error occurred sending ProposalApprovalEmail: ' . $th->getMessage());
        return false;
    }
}


function notifyCompanyDevice($info, $reason)
{
    $to = $info["companyNotificationToken"];
    $chatId = $info["chatId"];
    $proposalTotalPrice = $info["proposalTotalPrice"];
    $companyName = $info["companyName"];
    $companyProfilePic = $info["companyProfilePic"];
    $proposalResponseStatus = $info["proposalResponseStatus"];

    $privateKeyFile = '../../../firebase_settings.json';

    if (!file_exists($privateKeyFile)) {
        logMessage('Arquivo privateKeyFile.json não encontrado.');
        die('Arquivo privateKeyFile.json não encontrado.');
    }

    // Carrega a chave privada do JSON
    $privateKeyData = json_decode(file_get_contents($privateKeyFile), true);
    if (!$privateKeyData) {
        logMessage('Erro ao decodificar o arquivo JSON.');
        die('Erro ao decodificar o arquivo JSON.');
    }

    // Autenticação com OAuth 2.0
    $googleClient = new Google_Client();
    $googleClient->setAuthConfig($privateKeyFile);
    $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');

    if ($googleClient->isAccessTokenExpired()) {
        $googleClient->fetchAccessTokenWithAssertion();
    }

    $token = $googleClient->getAccessToken();

    if (!$token) {
        logMessage('Erro ao obter o token de acesso.');
        die('Erro ao obter o token de acesso.');
    }

    // Define o cabeçalho de autorização com o token OAuth 2.0
    $headers = [
        'Authorization' => 'Bearer ' . $token['access_token'],
        'Content-Type' => 'application/json',
    ];

    // URL do FCM
    $url = "https://fcm.googleapis.com/v1/projects/{$privateKeyData['project_id']}/messages:send";

    // Cria o payload da mensagem
    $messagePayload = [
        "message" => [
            "token" => $to,
            "data" => [
                "type" => "proposalResponse",
                "proposalTotalPrice" => (string) $proposalTotalPrice,
                "companyProfilePic" => (string) $companyProfilePic,
                "companyName" => (string) $companyName,
                "chatId" => (int) $chatId,
                "proposalResponseStatus" => (int) $proposalResponseStatus,
                "reasonText" => $reason,
            ],
        ],
    ];

    // Envia a mensagem
    $httpClient = new Client(['verify' => true]); // Verificação SSL ativada
    $response = $httpClient->post($url, [
        'headers' => $headers,
        'json' => $messagePayload,
    ]);

    if ($response->getStatusCode() !== 200) {
        logMessage('Erro ao enviar a notificação: ' . $response->getReasonPhrase());
        return false;
    } else {
        logMessage('Notificação enviada com sucesso.');
        return true;
    }
}

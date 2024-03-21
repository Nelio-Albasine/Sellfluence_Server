<?php
//Obtaining acces token.php file
session_start();

// Ativando a exibição de erros para desenvolvimento.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusão do arquivo de definições de constantes.
require_once 'defines.php';

// Carregamento do Facebook Graph SDK.
require_once __DIR__ . '/vendor/autoload.php';

// Array com credenciais do Facebook.
$creds = [
    'app_id' => FACEBOOK_APP_ID,
    'app_secret' => FACEBOOK_APP_SECRET,
    'default_graph_version' => 'v18.0', 
    'persistent_data_handler' => 'session'
];

$facebook = new Facebook\Facebook($creds);
$helper = $facebook->getRedirectLoginHelper();
$permissions = ['public_profile', 'instagram_basic', 'pages_show_list'];

// Cliente OAuth2.
$oAuth2Client = $facebook->getOAuth2Client();
$loginUrl = $helper->getLoginUrl(FACEBOOK_REDIRECT_URI, $permissions);

if(isset($_GET['userId'])) {
    $userId = $_GET['userId'];
    echo '<script>';
    echo 'localStorage.setItem("myId", ' . json_encode($userId) . ');';
    echo '</script>';
} else {
    echo '<script>';
    echo 'localStorage.setItem("myId", "emptyId");';
    echo '</script>';
}

if (isset($_GET['userId'])) {
    $userId = $_GET['userId'];
    echo '<script>';
    echo 'localStorage.setItem("myId", ' . json_encode($userId) . ');';
    echo '</script>';
} else {
    echo '<script>';
    echo 'localStorage.setItem("myId", "emptyId");';
    echo '</script>';
}


echo '
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

        <style>
            * {
                padding: 0;
                margin: 0;
                box-sizing: border-box;
            }

            body {
                background-color: #fafafa;
                justify-content: center;
                display: flex;
                font-size: 17px;
                flex-direction: column;
                margin: 0;
            }

            .header {
                width: 100vw;
                height: 60px;
                display: flex;
                align-items: center;
                background-color: blue;
                color: white;
            }

            .header img {
                margin: 10px 0 10px 12px;
                width: 40px;
                height: 40px;
            }

            .header h1 {
                margin-left: 15px;
                font-size: 24px;
            }

            .info {
                height: fit-content;
                width: 95%;
                padding: 20px;
                max-width: 650px;
                margin-top: 20px;
                align-self: center;
                background-color: white;
                border-radius: 10px;
                position: relative;
                top: 10px;
                left: 0;
                right: 0;
                bottom: 0;
                box-shadow: 1px 1px 2px #dfdfdf;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            #button-a{
                 align-self: center;
                 margin-top: 25px;
                 margin-top: 25px;
            }
            
            button {
                height: 40px;
                width: 250px;
                align-self: center;
                border-radius: 10px;
                background-color: #0c458f;
                color: white;
                font-size: 16px;
                border: none;
                box-shadow: 3px 2px 8px #dfdfdf;
                cursor: pointer;
                font-size: 15px;
            }

            button:hover {
                background-color: blue;
            }

            .fab {
                width: 45px;
                height: 45px;
                position: fixed;
                bottom: 10px;
                right: 20px;
                background-color: rgb(95, 95, 95);
                color: #fff;
                border: none;
                border-radius: 50%;
                padding: 10px;
                font-size: 20px;
                cursor: pointer;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .div-hr {
                display: flex;
                flex-direction: row;
                width: 100%;
                align-items: center;
                justify-content: center;
                margin-top: 40px;
            }

            hr {
                width: 35%;
                margin: 0 20px;
            }

            p {
                margin-top: 8px;
                margin-bottom: 8px;
                font-size: 16px;
                text-align: center;
                color: black;
            }

            li {
                margin-top: 5px;
                font-size: 14.5px;
                line-height: 17px;
                margin-left: 8px;
            }

            strong {
                color: blue;
                cursor: pointer;
                text-decoration: underline;
            }

            .secondary-p {
                font-size: 12.8px;
                margin-top: 20px;
                text-align: justify;
                align-self: center;
            }
        </style>
    </head>

    <body>
        <header class="header">
            <img src="https://img.icons8.com/color/48/Xy10Jcu1L2Su/instagram" alt="Sellfluence icon" />
            <h1>Sellfluence </h1>
        </header>

        <div class="info">
            <h3 style=" margin-bottom: 15px">Quais dados teremos acesso?</h3>
            <p >Ao vincular sua conta do Instagram ao nosso aplicativo, nós teremos acesso a:</p>
            <ul>
                <li>Quantidade de Seguidores que você tem!</li>
                <li>Métricas da conta como: Alcance, público e dentre <strong>outros!</strong></li>
                <li>Seu: nome, nome de usuário, ID da conta, foto de perfil!</li>
                <li>Suas 3 últimas publicações!</li>
            </ul>
            <p class="secondary-p">
                Para mais informações sobre quais dados serão coletados e como eles serão tratados, clique <strong>aqui</strong> e informe-se mais.
            </p>
        </div>

        <div class="div-hr">
            <hr>
            <img width="35" height="35" src="https://img.icons8.com/color/48/facebook-new.png" alt="facebook-new"/>
            <hr>
        </div>

        <a id="button-a" href="' . htmlspecialchars($loginUrl) . '">
            <button>
                Vincular minha conta
            </button>
        </a>

        <p style="margin: 10px 20px;" class="secondary-p">Para vincular a sua conta do Instagram, você <strong style="text-decoration: none;">NÃO</strong> precisará inserir suas credenciais do Instagram, mas sim as do Facebook. Saiba mais sobre isso <strong>aqui</strong>!</p>

        <div class="fab">
            <i class="fas fa-question-circle"></i>
        </div>
    </body>

    </html>';
?>

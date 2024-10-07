<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'defines.php';
require_once __DIR__ . '/vendor/autoload.php';

$creds = [
    'app_id' => FACEBOOK_APP_ID,
    'app_secret' => FACEBOOK_APP_SECRET,
    'default_graph_version' => 'v18.0',
    'persistent_data_handler' => 'session'
];

$facebook = new Facebook\Facebook($creds);
$helper = $facebook->getRedirectLoginHelper();
$oAuth2Client = $facebook->getOAuth2Client();
$accessToken = null;
$uid = null;
$username = null;
$name = null;

if (isset($_GET['state'])) {
    $helper->getPersistentDataHandler()->set('state', $_GET['state']);
    //this code will 
} else {
    echo 'The state parameter is not available in the URL';
}

try {
    // Obtém o access token usando o helper.
    $accessToken = $helper->getAccessToken();

    if (!$accessToken->isLongLived()) { // exchange short for long
        try {
            $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            $response = $facebook->get('/me?fields=id,name', $accessToken->getValue());
            $user = $response->getGraphUser();
            $uid = $user->getId();
            $name = $user->getName();

            /*   $url = "https://graph.instagram.com/v18.0/{$uid}?fields=id,username&access_token={$accessToken}";
               $ch = curl_init($url);
               curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
               $response = curl_exec($ch);
               curl_close($ch);
               $data = json_decode($response, true);
               if (isset($data['username'])) {
                   $username = $data['username'];
                   echo "Instagram username: $username";
               } else {
                    echo "Failed to retrieve username. Response: " . print_r($response, true);
               }   */


            echo 'Name: ' . $name . '<br>';
            echo 'Uid do user: ' . $uid . '<br>';
            //send to firestore


        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Tray catched about acces access token: ' . $e->getMessage();
        }
    } else {
        // O token já é de longa duração
    }
} catch (Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . htmlspecialchars($e->getMessage());
    error_log('Graph API error: ' . $e->getMessage() . ' in ' . __FILE__ . ' on line ' . __LINE__);
} catch (Facebook\Exceptions\FacebookSDKException $e) {
    echo 'Facebook SDK returned an error: ' . htmlspecialchars($e->getMessage());
    error_log('SDK error: ' . $e->getMessage() . ' in ' . __FILE__ . ' on line ' . __LINE__);
} catch (Exception $e) {
    echo 'Other error occurred: ' . htmlspecialchars($e->getMessage());
    error_log('General error: ' . $e->getMessage() . ' in ' . __FILE__ . ' on line ' . __LINE__);
}

echo '
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #fafafa;
            justify-content: center;
            align-items: center;
            display: flex;
            font-size: 17px;
            flex-direction: column;
        }

        div {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
            padding: 20px 50px;
            border-radius: 20px;
            box-shadow: 1px 1px 4px rgb(194, 194, 194);
            background-color: white;
        }

        img {
            width: 150px;
            height: 150px;
            margin-top: 50px;
        }

        h1 {
            color: green;
            margin: 20px 0 0 0;
        }

        h3 {
            color: green;
        }

        button {
            margin: 50px;
            padding: 8px 20px;
            background-color: green;
            border: none;
            border-radius: 20px;
            color: white;
            box-shadow: 1px 1px 2px green;
            transition: 0.3s;
        }

        button:hover {
            padding: 9px 22px;
        }

        @media only screen and (max-width: 728px) {
            body {
                font-size: 14px;
                background-color: white;
            }

            div {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 20px 50px;
                box-shadow: none;
                background-color: white;
            }

            p {
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>
    <div>
        <img src="res/feito.png" alt="Success done">
        <h1>Parabéns</h1>
        <h3>Conta vinculada com sucesso!</h3>

        <p>Access Token: ';
if ($accessToken) {
    echo htmlspecialchars($accessToken->getValue());
} else {
    echo 'Access Token não disponível';
}

echo '</p>

 <p id="myIdParagraph">
 
 <br>
 
 O UID do usuário do insta é: ';

if ($uid) {
    echo $uid;
} else {
    echo 'UID do insta não disponível';
}

echo '</p>

        <br>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let myId = localStorage.getItem("myId");

                if (myId) {
                    document.getElementById("myIdParagraph").textContent = "Valor de myId: " + myId;
                    setTimeout(function() {
                      localStorage.removeItem("myId");
                      console.log("Item removido após 50ms.");
                    }, 50);
                } else {
                    console.log("Nenhum valor encontrado no localStorage.");
                }
            });
        </script>

        <button>Voltar para o App</button>
    </div>
</body>

</html>';
?>
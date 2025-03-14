<?php
session_start();

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('error_log', __DIR__ . '/error_success_linked.log');

error_reporting(E_ALL);

require_once 'defines.php';
require_once __DIR__ . '/vendor/autoload.php';

// Importações necessárias do SDK de negócios
use FacebookAds\Api;

// Inicializando a API do Facebook
Api::init(FACEBOOK_APP_ID, FACEBOOK_APP_SECRET, null);

// Para debugging (opcional)
// Api::instance()->setLogger(new CurlLogger());

$accessToken = null;
$uid = null;
$name = null;

// Função para obter token de acesso do código de autorização
function getAccessTokenFromCode($code, $redirectUri) {
    $params = [
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri' => $redirectUri,
        'code' => $code
    ];
    
    $ch = curl_init('https://graph.facebook.com/v18.0/oauth/access_token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
    curl_setopt($ch, CURLOPT_POST, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('API Error: ' . $data['error']['message']);
    }
    
    return $data['access_token'];
}

// Função para trocar token de curta duração por longa duração
function getLongLivedAccessToken($accessToken) {
    $params = [
        'grant_type' => 'fb_exchange_token',
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'fb_exchange_token' => $accessToken
    ];
    
    $ch = curl_init('https://graph.facebook.com/v18.0/oauth/access_token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
    curl_setopt($ch, CURLOPT_POST, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('API Error: ' . $data['error']['message']);
    }
    
    return $data['access_token'];
}

// Função para obter dados do usuário
function getUserData($accessToken) {
    $url = "https://graph.facebook.com/v18.0/me?fields=id,name&access_token=" . urlencode($accessToken);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

try {
    if (isset($_GET['code'])) {
        // Obtém o token de acesso usando o código
        $accessToken = getAccessTokenFromCode($_GET['code'], FACEBOOK_REDIRECT_URI);
        
        // Converte para token de longa duração
        $longLivedToken = getLongLivedAccessToken($accessToken);
        
        // Atualiza o token
        $accessToken = $longLivedToken;
        error_log($accessToken);
        
        // Obtém informações do usuário
        $userData = getUserData($accessToken);
        
        if (isset($userData['id'])) {
            $uid = $userData['id'];
        }
        
        if (isset($userData['name'])) {
            $name = $userData['name'];
        }
        
        // Aqui você pode salvar o token no banco de dados ou em uma sessão
        // $_SESSION['fb_access_token'] = $accessToken;
    }
} catch (Exception $e) {
    echo 'Erro: ' . htmlspecialchars($e->getMessage());
    error_log('Erro: ' . $e->getMessage() . ' em ' . __FILE__ . ' na linha ' . __LINE__);
}

// Início do HTML
echo '
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conta Vinculada</title>

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
            cursor: pointer;
        }

        button:hover {
            padding: 9px 22px;
        }

        .token-display {
            max-width: 300px;
            word-break: break-all;
            margin: 10px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
            font-size: 12px;
            color: #333;
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
            
            .token-display {
                max-width: 280px;
            }
        }
    </style>
</head>

<body>
    <div>
        <img src="res/feito.png" alt="Success done">
        <h1>Parabéns</h1>
        <h3>Conta vinculada com sucesso!</h3>

        <p>Access Token:</p>
        <div class="token-display">';
        
if ($accessToken) {
    echo htmlspecialchars(substr($accessToken, 0, 50) . '...');
} else {
    echo 'Access Token não disponível';
}

echo '</div>

        <p>Usuário:</p>
        <div class="token-display">';

if ($name) {
    echo htmlspecialchars($name);
} else {
    echo 'Nome não disponível';
}

echo '</div>

        <p>ID do Facebook:</p>
        <div class="token-display">';

if ($uid) {
    echo htmlspecialchars($uid);
} else {
    echo 'ID não disponível';
}

echo '</div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let myId = localStorage.getItem("myId");

                if (myId) {
                    // Pode adicionar um elemento para exibir o ID aqui se necessário
                    console.log("ID do Usuário: " + myId);
                    
                    // Remove o ID após uso
                    setTimeout(function() {
                      localStorage.removeItem("myId");
                      console.log("Item removido após 50ms.");
                    }, 50);
                } else {
                    console.log("Nenhum valor encontrado no localStorage.");
                }
            });
            
            // Função para voltar ao app
            function voltarParaApp() {
                // Substitua pela URL real do seu app
                window.location.href = "/";
            }
        </script>

        <button onclick="voltarParaApp()">Voltar para o App</button>
    </div>
</body>

</html>';
?>
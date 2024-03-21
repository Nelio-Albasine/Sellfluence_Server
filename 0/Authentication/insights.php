<?php
include 'defines.php';

function makeApiCall($endpoint, $type, $params) {
    $ch = curl_init();

    if ('POST' == $type) {
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_POST, 1);
    } elseif ('GET' == $type) {
        curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// get user insights
$userInsightsEndpoint = ENDPOINT_BASE . $instagramAccountId . '/insights';

/*
-> follower_count: A contagem total de seguidores da conta.
-> impressions: O número total de vezes que todas as postagens foram vistas.
-> profile_views: O número de vezes que o perfil foi visualizado.
-> reach: O número de usuários únicos que viram qualquer uma das postagens, ou seja: Alcance da conta.
*/

$userInsightParams = array(
    'metric' => 'follower_count,impressions,profile_views,reach',
    'period' => 'day',
    'access_token' => $accessToken
);

##############
$userInsights = makeApiCall($userInsightsEndpoint, 'GET', $userInsightParams);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Instagram Instagram Graph API</title>
    <meta charset="utf-8" />
</head>
<body>
    <br />
    <h2>Usetr Insights</h2>
    <ul>
        <?php if (!empty($userInsights['data'])): ?>
            <?php foreach ($userInsights['data'] as $insight): ?>
                <li>
                    <div>
                        <b><?php echo $insight['title']; ?></b>
                    </div>
                    <div>
                        <?php foreach ($insight['values'] as $value): ?>
                            <div>Value: <?php echo $value['value']; ?> on <?php echo $value['end_time']; ?></div>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No user insights data available.</p>
        <?php endif; ?>
    </ul>
    <hr />
</body>
</html>

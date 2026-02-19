<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$clientId = $_ENV['POWERBI_CLIENT_ID'];
$clientSecret = $_ENV['POWERBI_CLIENT_SECRET'];
$tenantId = $_ENV['POWERBI_TENANT_ID'];
$workspaceId = $_ENV['POWERBI_WORKSPACE_ID'];
$reportId = $_ENV['POWERBI_REPORT_ID'];

echo "Fetching Access Token...\n";

$client = new Client();

try {
    $response = $client->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://analysis.windows.net/powerbi/api/.default',
        ]
    ]);

    $body = json_decode($response->getBody(), true);
    $accessToken = $body['access_token'];
    echo "Access Token acquired.\n";

    echo "Fetching Report Details...\n";

    $reportResponse = $client->get("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ]
    ]);

    $reportBody = json_decode($reportResponse->getBody(), true);
    $embedUrl = $reportBody['embedUrl'];
    $datasetId = $reportBody['datasetId'];

    echo "Embed URL: " . $embedUrl . "\n";
    echo "Report Dataset ID: " . $datasetId . "\n";

    echo "Fetching Datasets in Group...\n";
    $datasetsResponse = $client->get("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/datasets", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ]
    ]);

    $datasets = json_decode($datasetsResponse->getBody(), true)['value'];
    foreach ($datasets as $dataset) {
        if ($dataset['id'] === $datasetId) {
            echo "Target Dataset Found: " . $dataset['name'] . " (ID: " . $dataset['id'] . ")\n";
            echo "Is Effective Identity Required: " . ($dataset['isEffectiveIdentityRequired'] ?? 'N/A') . "\n";
            echo "Is Effective Identity Roles Required: " . ($dataset['isEffectiveIdentityRolesRequired'] ?? 'N/A') . "\n";
            echo "Is On Prem Gateway Required: " . ($dataset['isOnPremGatewayRequired'] ?? 'N/A') . "\n";
        }
    }

    echo "Generating Embed Token...\n";

    // Use API-provided URL to preserve query params
    $embedUrl = $reportBody['embedUrl'];

    // Safety check: Ensure hostname is correct (fix api-api if it ever appears, but keep params)
    if (strpos($embedUrl, 'https://api-api.powerbi.com') === 0) {
        $embedUrl = str_replace('https://api-api.powerbi.com', 'https://app.powerbi.com', $embedUrl);
    }
    echo "Embed URL (API + Safety Check): " . $embedUrl . "\n";

    // 2. Prepare Generate Token Request
    $tokenRequestData = [
        'accessLevel' => 'view',
        'datasetId' => $datasetId
    ];

    // Add RLS Identity if configured (mocking for debug)
    $rlsUsername = $_ENV['POWERBI_RLS_USERNAME'] ?: 'lucas@jrspicebrokers.onmicrosoft.com';
    $rlsRoles = $_ENV['POWERBI_RLS_ROLES'] ?: 'RLS_PagePermissions';

    echo "Using RLS Identity: Username=$rlsUsername, Roles=$rlsRoles\n";

    if ($rlsUsername && $rlsRoles) {
        $tokenRequestData['identities'] = [
            [
                'username' => $rlsUsername,
                'roles' => explode(',', $rlsRoles),
                'datasets' => [$datasetId]
            ]
        ];
    }

    echo "Generating Embed Token...\n";

    $tokenResponse = $client->post("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}/GenerateToken", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => $tokenRequestData
    ]);

    $tokenBody = json_decode($tokenResponse->getBody(), true);
    echo "Embed Token acquired.\n";
    echo "Token: " . $tokenBody['token'] . "\n"; // Uncommented for nuclear test

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n";
    }
}

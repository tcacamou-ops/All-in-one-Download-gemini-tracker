<?php
// Include the Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';
use AllI1D\GeminiTracker\Models\GeminiTrackerApiClient;

echo "Gemini Tracker listing test:\n";
$apiKey = getenv('GEMINI_TRACKER_API_KEY') ?: 'aKeyThatIsNotRealButLooksLikeOne';

$client = new GeminiTrackerApiClient($apiKey);

// As it comes from the cron flow.
$searchParams = [
    'title'        => 'cross',
    'type'         => 'tvshow',
    'saison'       => 2,
    'episode'      => 4,
    'found'        => false,
    'results'      => [],
    'audio_format' => 'VF',
];

// Transform parameters for the API.
$apiParams = [
    'name'    => $searchParams['title'],
    'type'    => $searchParams['type'],
    'saison'  => $searchParams['saison'],
    'episode' => $searchParams['episode'],
    'lang'    => 'VFF,TRUEFRENCH,FRENCH',
];

$result = $client->listTorrents($apiParams);
var_dump($result);

if (!empty($result['data'][0])) {
    $torrentFileContent = $client->downloadTorrent($result['data'][0]);
    echo "Downloaded torrent bytes: " . strlen((string) $torrentFileContent) . "\n";
}

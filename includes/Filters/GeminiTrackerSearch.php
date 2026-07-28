<?php
namespace AllI1D\GeminiTracker\Filters;

use AllI1D\GeminiTracker\Models\GeminiTrackerApiClient;
use AllI1D\Helpers\Crypto;

class GeminiTrackerSearch {

    public function __construct() {
    }

    public function search($results, $criteria) {
        $api_key = Crypto::decrypt( get_option('alli1d_gemini_tracker_api_key', '') );
        if ('' === $api_key) {
            $results['errors']['gemini_tracker'] = 'missing_credentials';
            return $results;
        }

        try {
            $apiClient = new GeminiTrackerApiClient($api_key);
            $items = $apiClient->searchTorrents($criteria);
            $results['items'] = array_merge($results['items'], $items);
        } catch (\Throwable $e) {
            $results['errors']['gemini_tracker'] = $e->getMessage();
        }

        return $results;
    }
}

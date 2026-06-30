<?php
namespace AllI1D\GeminiTracker\Filters;

use AllI1D\GeminiTracker\Models\GeminiTrackerApiClient;
use AllI1D\Helpers\Crypto;

class Status {

    public function __construct() {
    }

    public static function process_status($status) {
        $apiClient = new GeminiTrackerApiClient(
            Crypto::decrypt( get_option('alli1d_gemini_tracker_api_key', '') )
        );
        $is_connected = $apiClient->testConnection();

        if ($is_connected) {
            $retour = ['status' => 'connected', 'success' => 'Connection to Gemini Tracker API successful'];
        } else {
            $retour = [
                'error' => 'Failed to connect to Gemini Tracker API. Please check your API key.',
                'API connection' => $is_connected ? 'success' : 'failure',
            ];
        }
        $retour['settings_url'] = admin_url('admin.php?page=all-in-one-download-gemini-tracker');


        $status['GeminiTracker'] = $retour;
        return $status;
    }
}

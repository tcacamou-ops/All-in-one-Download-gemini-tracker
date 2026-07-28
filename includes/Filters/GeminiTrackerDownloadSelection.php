<?php
namespace AllI1D\GeminiTracker\Filters;

use AllI1D\GeminiTracker\Models\GeminiTrackerApiClient;
use AllI1D\Helpers\Crypto;

class GeminiTrackerDownloadSelection {

    public function __construct() {
    }

    public function download($null_default, $result) {
        try {
            $apiClient = new GeminiTrackerApiClient(Crypto::decrypt( get_option('alli1d_gemini_tracker_api_key', '') ));
            $file_content = $apiClient->downloadTorrent($result['extra']['torrent'] ?? $result['id']);
            if (null === $file_content) {
                return null;
            }

            $upload_dir = wp_upload_dir();
            $gemini_tracker_dir = $upload_dir['basedir'] . '/gemini-tracker';
            // Create the gemini-tracker directory if it doesn't exist
            if (!file_exists($gemini_tracker_dir)) {
                mkdir($gemini_tracker_dir, 0755, true);
            }
            $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $result['title'] ?? 'gemini_tracker')) . '.torrent';
            // Full path of the torrent file
            $file_path = $gemini_tracker_dir . '/' . $file_name;
            file_put_contents($file_path, $file_content);

            return [
                'type' => 'torrent',
                'path' => $file_path,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}

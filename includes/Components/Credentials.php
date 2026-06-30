<?php
namespace AllI1D\GeminiTracker\Components;

use AllI1D\Helpers\Crypto;

class Credentials {
    public function get_html(): string {
        ob_start();
        $this->render();
        return ob_get_clean() ?: '';
    }

    public function render() {
        echo '<label for="gemini_tracker_api_key">' . __('Gemini Tracker API Key', 'all-in-one-download-gemini-tracker') . '</label>';
        echo '<input type="password" id="gemini_tracker_api_key" name="gemini_tracker_api_key" placeholder="' . esc_attr( __( 'Gemini Tracker API Key', 'all-in-one-download-gemini-tracker' ) ) . '" required value="' . esc_attr( Crypto::decrypt( get_option( 'alli1d_gemini_tracker_api_key', '' ) ) ) . '" />';
        echo '<br /><br />';
        echo '<button type="button" id="submit-gemini-tracker-credentials">' . __('Save', 'all-in-one-download-gemini-tracker') . '</button>';
        echo '<div id="url-message" style="margin-top: 10px;"></div>';
    }
}

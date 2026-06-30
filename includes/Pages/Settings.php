<?php
namespace AllI1D\GeminiTracker\Pages;

use AllI1D\GeminiTracker\Components\Credentials;

class Settings {
    public function render() {
        $credentials = new Credentials();
        echo '<div class="wrap">';
        echo '<h1>' . __('Gemini Tracker Settings', 'all-in-one-download-gemini-tracker') . '</h1>';
        $credentials->render();

        echo '</div>';

    }
}

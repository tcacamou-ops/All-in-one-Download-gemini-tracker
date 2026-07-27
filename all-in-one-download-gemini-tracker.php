<?php
/**
 * Plugin Name: All-in-one Download Gemini Tracker
 * Plugin URI: https://github.com/tcacamou-ops/All-in-one-Download-gemini-tracker
 * Description: Add-on for All-in-one Download that allows downloading torrents from Gemini Tracker.
 * Version: 0.0.6
 * Author: tcacamou
 * Author URI: https://github.com/tcacamou-ops
 * Text Domain: all-in-one-download-gemini-tracker
 * Domain Path: /languages
 */

namespace AllI1D\GeminiTracker;

use AllI1D\GeminiTracker\Components\Credentials;
use AllI1D\GeminiTracker\Filters\GeminiTrackerMovies;
use AllI1D\GeminiTracker\Filters\GeminiTrackerTvShows;
use AllI1D\GeminiTracker\Filters\Status;
use AllI1D\Helpers\Crypto;
use honemo\updater\Updater;

// Security: prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

// Define the plugin's absolute path constant.
if (!defined('AllI1D_GEMINI_TRACKER_DIR')) {
    define('AllI1D_GEMINI_TRACKER_DIR', plugin_dir_path(__FILE__));
}

// Define the plugin's URL constant.
if (!defined('AllI1D_GEMINI_TRACKER_URL')) {
    define('AllI1D_GEMINI_TRACKER_URL', plugin_dir_url(__FILE__));
}

// Include Composer autoloader.
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

class Plugin {
    public function __construct() {
        $this->initialize_admin();
        $this->initialize_api();
        $this->initialize_filters();
    }

    private function initialize_admin() {
        if ( is_admin() ) {
            new Admin();
            $updater = new Updater(
                __FILE__,                                                            // Main plugin file.
                'https://github.com/tcacamou-ops/All-in-one-Download-gemini-tracker' // Repository URL.
            );

            $updater->init();
        }
    }

    private function initialize_api() {
        Api::get_instance();
    }

    private function initialize_filters() {
        $GeminiTrackerMovies  = new GeminiTrackerMovies();
        $GeminiTrackerTvShows = new GeminiTrackerTvShows();
        add_filter( 'alli1d_process_tvshow', [$GeminiTrackerTvShows, 'process_tv_show'] );
        add_filter( 'alli1d_process_movie', [$GeminiTrackerMovies, 'process_movie'] );
        add_filter( 'alli1d_process_status', [Status::class, 'process_status'] );
        add_filter( 'alli1d_provider_settings_modals', [$this, 'register_modal'] );
        add_action( 'admin_init', [$this, 'migrate_credentials_encryption'] );
    }

    public function migrate_credentials_encryption(): void {
        $migrated_key = 'alli1d_gemini_tracker_credentials_encrypted_v1';
        if ( get_option( $migrated_key ) ) {
            return;
        }
        $api_key = get_option( 'alli1d_gemini_tracker_api_key', '' );
        if ( '' !== $api_key && 0 !== strpos( $api_key, 'enc:' ) ) {
            update_option( 'alli1d_gemini_tracker_api_key', Crypto::encrypt( $api_key ) );
        }
        update_option( $migrated_key, true );
    }

    public function register_modal( array $modals ): array {
        $credentials = new Credentials();
        $modals['GeminiTracker'] = [
            'title' => __( 'Gemini Tracker Settings', 'all-in-one-download-gemini-tracker' ),
            'html'  => $credentials->get_html(),
        ];
        return $modals;
    }
}


// Initialize the plugin.
new Plugin();

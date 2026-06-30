<?php
namespace AllI1D\GeminiTracker\Api;

use AllI1D\Helpers\Crypto;

class CredentialsApi {

    private $route_namespace;

    private $current_namespace = 'credentials';

    public function __construct(string $route_namespace) {
        $this->route_namespace = $route_namespace;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function get_namespace(): string {
        return $this->route_namespace.'/'.$this->current_namespace;
    }

    public function check_permissions() :bool {
        return current_user_can('alli1d_admin');
    }

    public function get_routes():array {
        return [
            'credentials' => rest_url($this->get_namespace()),
        ];
    }

    public function register_routes() {
        register_rest_route(
            $this->route_namespace,
            $this->current_namespace,
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'set_credentials' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'gemini_tracker_api_key' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static fn( $v ) => is_string( $v ) && strlen( $v ) >= 8 && strlen( $v ) <= 512,
                    ],
                ],
            ]
        );
    }

    public function set_credentials($request) {
        $gemini_tracker_api_key = $request->get_param('gemini_tracker_api_key');
        update_option('alli1d_gemini_tracker_api_key', Crypto::encrypt( $gemini_tracker_api_key ));
        return new \WP_REST_Response(['status' => 'success'], 200);
    }
}

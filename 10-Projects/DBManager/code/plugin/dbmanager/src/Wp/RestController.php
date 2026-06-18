<?php

namespace DBM\Wp;

use DBM\Config\Settings;
use DBM\Rest\PingController;
use DBM\Sync\PayloadVerifier;

class RestController
{
    public function __construct(
        private Settings $settings,
        private \Closure $syncCallback
    ) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $controller = new PingController(new PayloadVerifier(), $this->settings->pingSecret, $this->syncCallback);

        register_rest_route('dbm/v1', '/ping', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function ($request) use ($controller) {
                $status = $controller->handle(
                    (string) $request->get_body(),
                    (string) $request->get_header('x-signature')
                );

                return new \WP_REST_Response(null, $status);
            },
        ]);
    }
}

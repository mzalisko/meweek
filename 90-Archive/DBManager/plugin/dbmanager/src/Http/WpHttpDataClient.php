<?php

namespace DBM\Http;

class WpHttpDataClient implements DataClient
{
    public function fetch(string $url, string $token, ?int $ifNoneMatchVersion): array
    {
        $headers = ['X-Site-Token' => $token];
        if ($ifNoneMatchVersion !== null) {
            $headers['If-None-Match'] = '"' . $ifNoneMatchVersion . '"';
        }

        $response = wp_remote_get($url, ['headers' => $headers, 'timeout' => 8]);

        if (is_wp_error($response)) {
            return ['status' => 0, 'body' => '', 'signature' => '', 'etag' => ''];
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
            'signature' => (string) wp_remote_retrieve_header($response, 'x-signature'),
            'etag' => (string) wp_remote_retrieve_header($response, 'etag'),
        ];
    }
}

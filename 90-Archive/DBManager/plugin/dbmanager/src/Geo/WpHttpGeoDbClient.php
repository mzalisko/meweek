<?php

namespace DBM\Geo;

class WpHttpGeoDbClient implements GeoDbClient
{
    /**
     * @return array{status:int, body:string, signature:string, etag:string}
     */
    public function fetch(string $url, string $token, ?string $ifNoneMatchSha): array
    {
        $headers = ['X-Site-Token' => $token];
        if ($ifNoneMatchSha !== null) {
            $headers['If-None-Match'] = '"' . $ifNoneMatchSha . '"';
        }

        $response = wp_remote_get($url, ['headers' => $headers, 'timeout' => 30]);

        if (is_wp_error($response)) {
            return ['status' => 0, 'body' => '', 'signature' => '', 'etag' => ''];
        }

        return [
            'status'    => (int) wp_remote_retrieve_response_code($response),
            'body'      => (string) wp_remote_retrieve_body($response),
            'signature' => (string) wp_remote_retrieve_header($response, 'x-signature'),
            'etag'      => (string) wp_remote_retrieve_header($response, 'etag'),
        ];
    }
}

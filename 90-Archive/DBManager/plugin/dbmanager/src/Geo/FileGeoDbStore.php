<?php

namespace DBM\Geo;

class FileGeoDbStore implements GeoDbStore
{
    private string $filePath;

    public function __construct()
    {
        $upload = wp_upload_dir();
        $dir    = rtrim((string) ($upload['basedir'] ?? sys_get_temp_dir()), '/\\');
        $this->filePath = $dir . '/dbm-geodb.mmdb';
    }

    public function sha(): ?string
    {
        return is_file($this->filePath) ? hash_file('sha256', $this->filePath) : null;
    }

    public function bytes(): ?string
    {
        return is_file($this->filePath) ? file_get_contents($this->filePath) : null;
    }

    public function put(string $bytes): void
    {
        file_put_contents($this->filePath, $bytes, LOCK_EX);
        // Persist resolved path so MaxMindCountryLookup can find it.
        update_option('dbm_geodb_path', $this->filePath, false);
    }

    public function path(): string
    {
        return $this->filePath;
    }
}

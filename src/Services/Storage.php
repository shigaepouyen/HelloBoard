<?php

class Storage {
    // Chemins relatifs depuis src/Services/ vers config/
    private static $configPath = __DIR__ . '/../../config/settings.json';
    private static $campaignsPath = __DIR__ . '/../../config/campaigns/';

    public static function saveGlobalSettings($settings) {
        $dir = dirname(self::$configPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return file_put_contents(self::$configPath, json_encode($settings, JSON_PRETTY_PRINT));
    }

    public static function getGlobalSettings() {
        if (!file_exists(self::$configPath)) return array();
        $content = file_get_contents(self::$configPath);
        return $content ? json_decode($content, true) : array();
    }

    public static function saveCampaign($slug, $data) {
        if (!is_dir(self::$campaignsPath)) mkdir(self::$campaignsPath, 0755, true);
        $filename = self::$campaignsPath . $slug . '.json';
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function listCampaigns() {
        if (!is_dir(self::$campaignsPath)) return array();
        $files = glob(self::$campaignsPath . '*.json');
        $campaigns = array();
        if ($files) {
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content) {
                    $campaigns[] = json_decode($content, true);
                }
            }
        }
        return $campaigns;
    }

    // --- NOUVEAU : SUPPRESSION ---
    public static function deleteCampaign($slug) {
        $filename = self::$campaignsPath . basename($slug) . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
}
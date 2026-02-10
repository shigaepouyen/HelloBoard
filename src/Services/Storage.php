<?php

class Storage {
    // Chemins relatifs depuis src/Services/ vers config/
    private static $configPath = __DIR__ . '/../../config/settings.json';
    private static $campaignsPath = __DIR__ . '/../../config/campaigns/';
    private static $checkinsPath = __DIR__ . '/../../config/checkins/';
    private static $mailingPath = __DIR__ . '/../../config/mailing/';
    private static $attachmentsPath = __DIR__ . '/../../config/mailing/attachments/';

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
            $checkinFile = self::$checkinsPath . basename($slug) . '.json';
            if (file_exists($checkinFile)) unlink($checkinFile);
            $mailingFile = self::$mailingPath . basename($slug) . '.json';
            if (file_exists($mailingFile)) unlink($mailingFile);

            $attachDir = self::$attachmentsPath . basename($slug) . '/';
            if (is_dir($attachDir)) {
                $files = glob($attachDir . '*');
                foreach($files as $f) if(is_file($f)) unlink($f);
                rmdir($attachDir);
            }

            return unlink($filename);
        }
        return false;
    }

    public static function saveCheckins($slug, $checkins) {
        if (!is_dir(self::$checkinsPath)) mkdir(self::$checkinsPath, 0755, true);
        $filename = self::$checkinsPath . basename($slug) . '.json';
        // Use a temporary file and rename for atomicity, or flock.
        // Flock is safer for concurrent reads on the same file.
        $fp = fopen($filename, "c");
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($checkins, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function getCheckins($slug) {
        $filename = self::$checkinsPath . basename($slug) . '.json';
        if (!file_exists($filename)) return array();

        $fp = fopen($filename, "r");
        $content = "";
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return $content ? json_decode($content, true) : array();
    }

    public static function saveMailingHistory($slug, $history) {
        if (!is_dir(self::$mailingPath)) mkdir(self::$mailingPath, 0755, true);
        $filename = self::$mailingPath . basename($slug) . '.json';
        $fp = fopen($filename, "c");
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($history, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function getMailingHistory($slug) {
        $filename = self::$mailingPath . basename($slug) . '.json';
        if (!file_exists($filename)) return array();
        $fp = fopen($filename, "r");
        $content = "";
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $content ? json_decode($content, true) : array();
    }

    public static function saveMailingAttachment($slug, $file) {
        $dir = self::$attachmentsPath . basename($slug) . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $target = $dir . basename($file['name']);
        return move_uploaded_file($file['tmp_name'], $target);
    }

    public static function listMailingAttachments($slug) {
        $dir = self::$attachmentsPath . basename($slug) . '/';
        if (!is_dir($dir)) return [];
        $files = glob($dir . '*');
        $result = [];
        foreach($files as $f) {
            if (is_file($f)) {
                $result[] = [
                    'name' => basename($f),
                    'path' => $f,
                    'size' => filesize($f)
                ];
            }
        }
        return $result;
    }

    public static function deleteMailingAttachment($slug, $filename) {
        $file = self::$attachmentsPath . basename($slug) . '/' . basename($filename);
        if (file_exists($file)) return unlink($file);
        return false;
    }
}
<?php
require_once 'config.php';

/**
 * سیستم کش یکپارچه برای افزایش سرعت
 */
class CacheSystem {
    
    private static $instance = null;
    private $memory_cache = [];
    private $file_cache_enabled = true;
    
    private function __construct() {
        // جلوگیری از ایجاد نمونه مستقیم
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ذخیره داده در کش دو سطحی
     */
    public function set($key, $value, $ttl_seconds = 300) {
        $expire_time = time() + $ttl_seconds;
        
        // سطح 1: کش حافظه
        $this->memory_cache[$key] = [
            'value' => $value,
            'expire' => $expire_time,
            'size' => $this->calculateSize($value)
        ];
        
        // سطح 2: کش فایل
        if ($this->file_cache_enabled) {
            $this->setFileCache($key, $value, $expire_time);
        }
        
        return true;
    }
    
    /**
     * دریافت داده از کش
     */
    public function get($key) {
        // اول بررسی کش حافظه
        if (isset($this->memory_cache[$key])) {
            $item = $this->memory_cache[$key];
            if ($item['expire'] > time()) {
                return $item['value'];
            }
            unset($this->memory_cache[$key]);
        }
        
        // سپس بررسی کش فایل
        if ($this->file_cache_enabled) {
            $file_data = $this->getFileCache($key);
            if ($file_data !== null) {
                // ذخیره در کش حافظه برای دسترسی بعدی
                $this->memory_cache[$key] = [
                    'value' => $file_data['value'],
                    'expire' => $file_data['expire'],
                    'size' => $this->calculateSize($file_data['value'])
                ];
                return $file_data['value'];
            }
        }
        
        return null;
    }
    
    /**
     * حذف از کش
     */
    public function delete($key) {
        unset($this->memory_cache[$key]);
        
        if ($this->file_cache_enabled) {
            $this->deleteFileCache($key);
        }
        
        return true;
    }
    
    /**
     * پاکسازی کامل کش
     */
    public function clear() {
        $this->memory_cache = [];
        
        if ($this->file_cache_enabled) {
            $this->clearFileCache();
        }
        
        return true;
    }
    
    /**
     * ذخیره در کش فایل
     */
    private function setFileCache($key, $value, $expire_time) {
        $cache_file = DATA_DIR . 'cache/' . md5($key) . '.cache';
        $cache_dir = dirname($cache_file);
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $cache_data = [
            'value' => $value,
            'expire' => $expire_time,
            'created' => time(),
            'key' => $key
        ];
        
        $serialized = serialize($cache_data);
        $compressed = function_exists('gzcompress') ? gzcompress($serialized) : $serialized;
        
        file_put_contents($cache_file, $compressed);
        
        // به‌روزرسانی فایل index
        $this->updateCacheIndex($key, $expire_time, strlen($compressed));
    }
    
    /**
     * دریافت از کش فایل
     */
    private function getFileCache($key) {
        $cache_file = DATA_DIR . 'cache/' . md5($key) . '.cache';
        
        if (!file_exists($cache_file)) {
            return null;
        }
        
        $content = file_get_contents($cache_file);
        $serialized = function_exists('gzuncompress') ? @gzuncompress($content) : $content;
        
        if ($serialized === false) {
            unlink($cache_file);
            return null;
        }
        
        $cache_data = @unserialize($serialized);
        
        if (!$cache_data || !isset($cache_data['expire'])) {
            unlink($cache_file);
            return null;
        }
        
        // بررسی انقضا
        if ($cache_data['expire'] < time()) {
            unlink($cache_file);
            $this->removeFromCacheIndex($key);
            return null;
        }
        
        return $cache_data;
    }
    
    /**
     * حذف از کش فایل
     */
    private function deleteFileCache($key) {
        $cache_file = DATA_DIR . 'cache/' . md5($key) . '.cache';
        
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        
        $this->removeFromCacheIndex($key);
    }
    
    /**
     * پاکسازی کش فایل
     */
    private function clearFileCache() {
        $cache_dir = DATA_DIR . 'cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.cache');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        // پاک کردن فایل index
        $index_file = DATA_DIR . 'cache_index.json';
        if (file_exists($index_file)) {
            unlink($index_file);
        }
    }
    
    /**
     * به‌روزرسانی فهرست کش
     */
    private function updateCacheIndex($key, $expire_time, $size) {
        $index_file = DATA_DIR . 'cache_index.json';
        
        if (file_exists($index_file)) {
            $index = json_decode(file_get_contents($index_file), true);
        } else {
            $index = [];
        }
        
        $index[$key] = [
            'expire' => $expire_time,
            'size' => $size,
            'updated' => time()
        ];
        
        // محدود کردن تعداد آیتم‌ها
        if (count($index) > 1000) {
            $this->cleanupCacheIndex($index);
        }
        
        file_put_contents($index_file, json_encode($index, JSON_PRETTY_PRINT));
    }
    
    /**
     * حذف از فهرست کش
     */
    private function removeFromCacheIndex($key) {
        $index_file = DATA_DIR . 'cache_index.json';
        
        if (file_exists($index_file)) {
            $index = json_decode(file_get_contents($index_file), true);
            if (isset($index[$key])) {
                unset($index[$key]);
                file_put_contents($index_file, json_encode($index, JSON_PRETTY_PRINT));
            }
        }
    }
    
    /**
     * پاکسازی فهرست کش
     */
    private function cleanupCacheIndex(&$index) {
        // مرتب‌سازی بر اساس زمان به‌روزرسانی
        uasort($index, function($a, $b) {
            return $a['updated'] - $b['updated'];
        });
        
        // حذف قدیمی‌ترین‌ها تا تعداد به 800 برسد
        while (count($index) > 800) {
            array_shift($index);
        }
    }
    
    /**
     * محاسبه اندازه داده
     */
    private function calculateSize($data) {
        return strlen(serialize($data));
    }
    
    /**
     * دریافت آمار کش
     */
    public function getStats() {
        $stats = [
            'memory_items' => count($this->memory_cache),
            'memory_size' => 0,
            'file_items' => 0,
            'file_size' => 0
        ];
        
        // محاسبه اندازه کش حافظه
        foreach ($this->memory_cache as $item) {
            $stats['memory_size'] += $item['size'];
        }
        
        // محاسبه آمار کش فایل
        $index_file = DATA_DIR . 'cache_index.json';
        if (file_exists($index_file)) {
            $index = json_decode(file_get_contents($index_file), true);
            $stats['file_items'] = count($index);
            $stats['file_size'] = array_sum(array_column($index, 'size'));
        }
        
        $stats['total_items'] = $stats['memory_items'] + $stats['file_items'];
        $stats['total_size'] = $stats['memory_size'] + $stats['file_size'];
        
        return $stats;
    }
    
    /**
     * فعال/غیرفعال کردن کش فایل
     */
    public function setFileCacheEnabled($enabled) {
        $this->file_cache_enabled = $enabled;
    }
}
?>
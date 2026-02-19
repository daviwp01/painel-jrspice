<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) return $default;

        $value = $setting->value;

        // Auto-cast booleans
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        // Auto-decode JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Set a setting value by key.
     */
    public static function set($key, $value)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

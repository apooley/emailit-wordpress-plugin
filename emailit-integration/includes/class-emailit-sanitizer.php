<?php
/**
 * Emailit Sanitizer Helper Class
 *
 * Provides consistent sanitization and escaping methods following WordPress best practices.
 * Implements "sanitize early, escape late" pattern.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_Sanitizer {

    /**
     * Sanitize input data based on type
     *
     * @param mixed $input The input to sanitize
     * @param string $type The type of sanitization to apply
     * @return mixed Sanitized input
     */
    public static function sanitize_input($input, $type = 'text') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitize_input($item, $type);
            }, $input);
        }

        if (is_null($input)) {
            return null;
        }

        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'url':
                return esc_url_raw($input);
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'boolean':
                return (bool) $input;
            case 'html':
                return wp_kses_post($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'key':
                return sanitize_key($input);
            case 'filename':
                return sanitize_file_name($input);
            case 'sql':
                return esc_sql($input);
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Validate input against whitelist
     *
     * @param mixed $input The input to validate
     * @param array $whitelist Array of allowed values
     * @param mixed $default Default value if input not in whitelist
     * @return mixed Validated input or default
     */
    public static function validate_whitelist($input, $whitelist, $default = null) {
        if (in_array($input, $whitelist, true)) {
            return $input;
        }
        return $default;
    }

    /**
     * Sanitize and validate email address
     *
     * @param string $email Email address to sanitize
     * @return string|false Sanitized email or false if invalid
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }

    /**
     * Sanitize multiple email addresses
     *
     * @param string|array $emails Email addresses to sanitize
     * @return array Array of valid email addresses
     */
    public static function sanitize_emails($emails) {
        if (is_string($emails)) {
            $emails = explode(',', $emails);
        }

        $valid_emails = array();
        foreach ($emails as $email) {
            $email = trim($email);
            $sanitized = self::sanitize_email($email);
            if ($sanitized) {
                $valid_emails[] = $sanitized;
            }
        }

        return $valid_emails;
    }

    /**
     * Sanitize HTML content with allowed tags
     *
     * @param string $html HTML content to sanitize
     * @param array $allowed_tags Allowed HTML tags (optional)
     * @return string Sanitized HTML
     */
    public static function sanitize_html($html, $allowed_tags = null) {
        if ($allowed_tags === null) {
            // Default allowed tags for email content
            $allowed_tags = array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'em' => array(),
                'u' => array(),
                'a' => array('href' => array(), 'title' => array()),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'h1' => array(),
                'h2' => array(),
                'h3' => array(),
                'h4' => array(),
                'h5' => array(),
                'h6' => array(),
                'img' => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array()),
                'table' => array(),
                'tr' => array(),
                'td' => array(),
                'th' => array(),
                'thead' => array(),
                'tbody' => array(),
                'tfoot' => array(),
            );
        }

        return wp_kses($html, $allowed_tags);
    }

    /**
     * Escape output for HTML context
     *
     * @param string $output Output to escape
     * @param string $context Context for escaping (html, attr, url, js)
     * @return string Escaped output
     */
    public static function escape_output($output, $context = 'html') {
        switch ($context) {
            case 'attr':
                return esc_attr($output);
            case 'url':
                return esc_url($output);
            case 'js':
                return esc_js($output);
            case 'html':
            default:
                return esc_html($output);
        }
    }

    /**
     * Sanitize file path to prevent directory traversal
     *
     * @param string $path File path to sanitize
     * @return string Sanitized file path
     */
    public static function sanitize_file_path($path) {
        // Remove any null bytes
        $path = str_replace(chr(0), '', $path);
        
        // Normalize path separators
        $path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
        
        // Remove directory traversal attempts
        $path = preg_replace('/\.\./', '', $path);
        
        // Remove multiple consecutive separators
        $path = preg_replace('/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '+/', DIRECTORY_SEPARATOR, $path);
        
        return trim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Sanitize database table name
     *
     * @param string $table_name Table name to sanitize
     * @return string Sanitized table name
     */
    public static function sanitize_table_name($table_name) {
        // Remove any non-alphanumeric characters except underscore
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        
        // Ensure it doesn't start with a number
        if (preg_match('/^[0-9]/', $table_name)) {
            $table_name = 't_' . $table_name;
        }
        
        return $table_name;
    }

    /**
     * Sanitize database column name
     *
     * @param string $column_name Column name to sanitize
     * @return string Sanitized column name
     */
    public static function sanitize_column_name($column_name) {
        // Remove any non-alphanumeric characters except underscore
        $column_name = preg_replace('/[^a-zA-Z0-9_]/', '', $column_name);
        
        // Ensure it doesn't start with a number
        if (preg_match('/^[0-9]/', $column_name)) {
            $column_name = 'c_' . $column_name;
        }
        
        return $column_name;
    }

    /**
     * Sanitize JSON data
     *
     * @param mixed $data Data to sanitize for JSON
     * @return mixed Sanitized data
     */
    public static function sanitize_json($data) {
        if (is_string($data)) {
            return self::sanitize_input($data, 'text');
        } elseif (is_array($data)) {
            return array_map(array(__CLASS__, 'sanitize_json'), $data);
        } elseif (is_object($data)) {
            $sanitized = new stdClass();
            foreach ($data as $key => $value) {
                $sanitized->{self::sanitize_input($key, 'key')} = self::sanitize_json($value);
            }
            return $sanitized;
        }
        
        return $data;
    }

    /**
     * Validate and sanitize date format
     *
     * @param string $date Date string to validate
     * @param string $format Expected date format (default: Y-m-d)
     * @return string|false Valid date string or false if invalid
     */
    public static function sanitize_date($date, $format = 'Y-m-d') {
        $date = self::sanitize_input($date, 'text');
        
        $d = DateTime::createFromFormat($format, $date);
        if ($d && $d->format($format) === $date) {
            return $date;
        }
        
        return false;
    }

    /**
     * Sanitize and validate numeric range
     *
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param int $default Default value if out of range
     * @return int Validated value
     */
    public static function sanitize_numeric_range($value, $min, $max, $default = 0) {
        $value = intval($value);
        
        if ($value < $min || $value > $max) {
            return $default;
        }
        
        return $value;
    }

    /**
     * Sanitize HTTP headers
     *
     * @param string $header Header value to sanitize
     * @return string Sanitized header
     */
    public static function sanitize_header($header) {
        // Remove null bytes and control characters
        $header = preg_replace('/[\x00-\x1F\x7F]/', '', $header);
        
        // Remove CRLF injection attempts
        $header = preg_replace('/[\r\n]/', '', $header);
        
        // Trim whitespace
        $header = trim($header);
        
        return $header;
    }

    /**
     * Sanitize array of data with specified types
     *
     * @param array $data Data array to sanitize
     * @param array $types Array mapping keys to sanitization types
     * @return array Sanitized data array
     */
    public static function sanitize_array($data, $types) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            $type = isset($types[$key]) ? $types[$key] : 'text';
            $sanitized[$key] = self::sanitize_input($value, $type);
        }
        
        return $sanitized;
    }
}


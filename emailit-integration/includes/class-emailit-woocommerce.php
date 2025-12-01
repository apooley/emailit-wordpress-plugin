<?php
/**
 * Emailit WooCommerce Integration Class
 *
 * Handles WooCommerce checkout opt-in and audience subscription functionality.
 * Only activates when both WooCommerce is active AND feature is enabled in settings.
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class Emailit_WooCommerce {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * API instance
     */
    private $api;

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance($logger = null, $api = null) {
        if (null === self::$instance) {
            self::$instance = new self($logger, $api);
        }
        return self::$instance;
    }

    /**
     * Constructor - Private to prevent direct instantiation
     * Dual guard: Only initialize if WooCommerce is active AND feature is enabled
     */
    private function __construct($logger = null, $api = null) {
        // Dual guard: Both conditions must be true
        if (!class_exists('WooCommerce')) {
            return; // WooCommerce not active
        }

        if (!get_option('emailit_wc_enabled', false)) {
            return; // Feature not enabled
        }

        // Both conditions met - proceed with initialization
        $this->logger = $logger;
        $this->api = $api;
        $this->init_hooks();
    }

    /**
     * Check if WooCommerce integration is enabled
     * 
     * @return bool True if both WooCommerce is active and feature is enabled
     */
    public static function is_enabled() {
        return class_exists('WooCommerce') && get_option('emailit_wc_enabled', false);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

        // Checkout hooks
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_field'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_meta'));
        add_action('woocommerce_checkout_before_terms_and_conditions', array($this, 'render_checkout_field'));

        // Enqueue checkout script to detect custom checkbox
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));

        // Process subscription after order is created
        add_action('woocommerce_checkout_order_processed', array($this, 'process_emailit_subscription'), 10, 3);

        // AJAX handlers for batch processing
        add_action('wp_ajax_emailit_wc_get_pending_count', array($this, 'ajax_get_pending_count'));
        add_action('wp_ajax_emailit_wc_process_pending', array($this, 'ajax_process_pending_orders'));
        add_action('wp_ajax_emailit_wc_store_total_count', array($this, 'ajax_store_total_count'));
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', EMAILIT_PLUGIN_FILE, true);
        }
    }

    /**
     * Enqueue checkout scripts to detect custom checkbox
     */
    public function enqueue_checkout_scripts() {
        if (!is_checkout()) {
            return;
        }

        // Only enqueue if feature is enabled
        if (!self::is_enabled()) {
            return;
        }

        wp_enqueue_script(
            'emailit-wc-checkout',
            EMAILIT_PLUGIN_URL . 'admin/assets/js/woocommerce-checkout.js',
            array('jquery', 'wc-checkout'),
            EMAILIT_VERSION,
            true
        );
    }

    /**
     * Render checkout field
     * Only renders if feature is enabled
     */
    public function render_checkout_field() {
        // Guard: Only render if enabled
        if (!self::is_enabled()) {
            return;
        }

        $checkbox_label = get_option('emailit_wc_checkbox_label', __('Subscribe to our newsletter', 'emailit-integration'));
        $checkbox_default = get_option('emailit_wc_checkbox_default', false);

        // Use WooCommerce form field function for proper rendering
        woocommerce_form_field('emailit_subscribe', array(
            'type'        => 'checkbox',
            'class'       => array('form-row', 'mycheckbox'),
            'label'       => esc_html($checkbox_label),
            'required'    => false,
            'default'     => $checkbox_default ? 1 : 0,
        ), $checkbox_default ? 1 : 0);
    }

    /**
     * Save checkout field value
     * Detects custom checkbox with class .form-row.mycheckbox
     */
    public function save_checkout_field($order_id) {
        // Validate order ID
        $order_id = absint($order_id);
        if (!$order_id) {
            return;
        }

        // Get order object for HPOS compatibility
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check for emailit_subscribe hidden field (set by JavaScript from custom checkbox)
        // Sanitize input
        $subscribe = isset($_POST['emailit_subscribe']) ? absint($_POST['emailit_subscribe']) : 0;

        $order->update_meta_data('_emailit_subscribe', $subscribe);
        $order->save();
    }

    /**
     * Display order meta in admin
     */
    public function display_order_meta($order) {
        // WooCommerce handles capability checks, but verify order object
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // Use order object methods for HPOS compatibility
        $subscribe = $order->get_meta('_emailit_subscribe');
        $subscribed = $order->get_meta('_emailit_subscribed');
        $subscription_status = $order->get_meta('_emailit_subscription_status');
        $subscription_date = $order->get_meta('_emailit_subscription_date');
        $subscriber_id = $order->get_meta('_emailit_subscriber_id');
        $error = $order->get_meta('_emailit_subscription_error');

        // Build status text safely
        $status_parts = array();

        if ($subscribe) {
            if ('yes' === $subscribed) {
                $status_parts[] = esc_html__('Subscribed', 'emailit-integration');

                if ('existing' === $subscription_status) {
                    $status_parts[] = '(' . esc_html__('Already existed', 'emailit-integration') . ')';
                }

                if ($subscription_date) {
                    $formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription_date));
                    $status_parts[] = esc_html($formatted_date);
                }

                if ($subscriber_id) {
                    $status_parts[] = esc_html__('ID:', 'emailit-integration') . ' ' . esc_html($subscriber_id);
                }
            } elseif ($error) {
                $status_parts[] = esc_html__('Failed', 'emailit-integration') . ': ' . esc_html($error);
            } else {
                $status_parts[] = esc_html__('Pending', 'emailit-integration');
            }
        } else {
            $status_parts[] = esc_html__('No', 'emailit-integration');
        }

        ?>
        <div class="address">
            <p>
                <strong><?php esc_html_e('Emailit Subscription:', 'emailit-integration'); ?></strong>
                <?php echo esc_html(implode(' ', $status_parts)); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Process EmailIt subscription
     */
    public function process_emailit_subscription($order_id, $posted_data, $order) {
        // Validate order ID and order object
        $order_id = absint($order_id);
        if (!$order_id || !is_a($order, 'WC_Order')) {
            return;
        }

        // Use order object methods for HPOS compatibility
        $subscribe = $order->get_meta('_emailit_subscribe');

        if (!$subscribe) {
            return;
        }

        // Get decrypted API key using static method (handles encryption/decryption)
        // This ensures we use the decrypted key even when API class instance is unavailable
        $api_key = '';
        if (class_exists('Emailit_API')) {
            $api_key = Emailit_API::get_decrypted_api_key();
        } else {
            // Fallback: try to get API instance via component system
            $api = emailit_get_component('api');
            if ($api && method_exists($api, 'get_api_key')) {
                $api_key = $api->get_api_key();
            }
        }

        $audience_id = get_option('emailit_wc_audience_id', '');

        if (empty($api_key) || empty($audience_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Emailit WooCommerce: API key or Audience ID not configured.');
            }
            return;
        }

        $email = $order->get_billing_email();
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        if (empty($email) || !is_email($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Emailit WooCommerce: No valid email address found for order ' . $order_id);
            }
            return;
        }

        $this->subscribe_to_emailit($api_key, $audience_id, $email, $first_name, $last_name, $order);
    }

    /**
     * Subscribe user to EmailIt audience
     * Uses EmailIt API v2: POST /v2/audiences/{audience_id}/subscribers
     */
    private function subscribe_to_emailit($api_key, $audience_id, $email, $first_name = '', $last_name = '', $order = null) {
        // Resolve API instance (decrypts stored key)
        $api_instance = $this->api ?: emailit_get_component('api');
        if (!$api_instance && class_exists('Emailit_API')) {
            $api_instance = new Emailit_API($this->logger);
        }

        // Use API class if available, otherwise make direct request
        if ($api_instance && method_exists($api_instance, 'subscribe_to_audience')) {
            $result = $api_instance->subscribe_to_audience($audience_id, $email, $first_name, $last_name);
            
            // Handle result and update order meta
            if (is_wp_error($result)) {
                if (is_a($order, 'WC_Order')) {
                    $order->update_meta_data('_emailit_subscription_error', sanitize_text_field($result->get_error_message()));
                    $order->save();
                }
                if ($this->logger) {
                    $this->logger->log('WooCommerce subscription failed: ' . $result->get_error_message(), 'error', array(
                        'email' => $email,
                        'audience_id' => $audience_id
                    ));
                }
                return false;
            }

            // Success - update order meta
            if (is_a($order, 'WC_Order')) {
                $subscription_status = isset($result['status']) && $result['status'] === 'existing' ? 'existing' : 'new';
                $order->update_meta_data('_emailit_subscribed', 'yes');
                $order->update_meta_data('_emailit_subscription_status', $subscription_status);
                $order->update_meta_data('_emailit_subscription_date', current_time('mysql'));
                
                if (isset($result['subscriber_id'])) {
                    $order->update_meta_data('_emailit_subscriber_id', sanitize_text_field($result['subscriber_id']));
                }
                
                $order->save();
            }

            if ($this->logger) {
                $this->logger->log('WooCommerce subscription successful', 'info', array(
                    'email' => $email,
                    'audience_id' => $audience_id,
                    'status' => $subscription_status
                ));
            }
            
            return true;
        }

        // Fallback: Direct API call if API class method not available
        // Ensure we have a decrypted API key; if not, abort gracefully
        if ($api_instance && method_exists($api_instance, 'get_api_key')) {
            $api_key = $api_instance->get_api_key();
        }

        if (empty($api_key)) {
            if ($this->logger) {
                $this->logger->log('WooCommerce subscription failed: API key unavailable for fallback request.', 'error', array(
                    'email' => $email,
                    'audience_id' => $audience_id
                ));
            }
            if (is_a($order, 'WC_Order')) {
                $order->update_meta_data('_emailit_subscription_error', __('API key unavailable', 'emailit-integration'));
                $order->save();
            }
            return false;
        }

        // Note: $api_key is already decrypted at this point
        $api_url = 'https://api.emailit.com/v2/audiences/' . sanitize_text_field($audience_id) . '/subscribers';

        $body = array(
            'email' => sanitize_email($email),
        );

        if (!empty($first_name)) {
            $body['first_name'] = sanitize_text_field($first_name);
        }

        if (!empty($last_name)) {
            $body['last_name'] = sanitize_text_field($last_name);
        }

        // Use decrypted API key directly (already sanitized/validated)
        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key, // Key is already decrypted, no need for sanitize_text_field
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        );

        $response = wp_remote_request($api_url, $args);

        // Get order object if not provided
        if (!is_a($order, 'WC_Order') && is_numeric($order)) {
            $order = wc_get_order(absint($order));
        }

        if (is_wp_error($response)) {
            $error_message = sanitize_text_field($response->get_error_message());
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EmailIt WooCommerce API Error: ' . $error_message);
            }
            if (is_a($order, 'WC_Order')) {
                $order->update_meta_data('_emailit_subscription_error', $error_message);
                $order->save();
            }
            return false;
        }

        $response_code = absint(wp_remote_retrieve_response_code($response));
        $response_body = wp_remote_retrieve_body($response);

        // Handle response codes: 201 = Created, 409 = Conflict (already exists)
        if (201 === $response_code || 409 === $response_code) {
            $subscription_status = (409 === $response_code) ? 'existing' : 'new';
            if (is_a($order, 'WC_Order')) {
                $order->update_meta_data('_emailit_subscribed', 'yes');
                $order->update_meta_data('_emailit_subscription_status', $subscription_status);
                $order->update_meta_data('_emailit_subscription_date', current_time('mysql'));

                // Parse response to get subscriber ID if available
                $response_data = json_decode($response_body, true);
                if (is_array($response_data) && isset($response_data['id'])) {
                    $order->update_meta_data('_emailit_subscriber_id', sanitize_text_field($response_data['id']));
                }

                $order->save();
            }

            return true;
        }

        // Handle error responses
        $error_message = $this->get_api_error_message($response_code, $response_body);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EmailIt WooCommerce API Error: HTTP ' . $response_code . ' - ' . $error_message);
        }
        if (is_a($order, 'WC_Order')) {
            $order->update_meta_data('_emailit_subscription_error', 'HTTP ' . $response_code . ': ' . $error_message);
            $order->save();
        }
        return false;
    }

    /**
     * Get user-friendly error message from API response
     */
    private function get_api_error_message($response_code, $response_body) {
        $response_data = json_decode($response_body, true);

        if (is_array($response_data)) {
            if (isset($response_data['message']) && is_string($response_data['message'])) {
                return sanitize_text_field($response_data['message']);
            }

            if (isset($response_data['error']) && is_string($response_data['error'])) {
                return sanitize_text_field($response_data['error']);
            }
        }

        // Default messages based on status code
        switch ($response_code) {
            case 400:
                return __('Bad Request - Invalid data provided', 'emailit-integration');
            case 401:
                return __('Unauthorized - Invalid API key', 'emailit-integration');
            case 404:
                return __('Not Found - Audience does not exist', 'emailit-integration');
            case 422:
                return __('Unprocessable Entity - Validation error', 'emailit-integration');
            case 500:
                return __('Internal Server Error - EmailIt server error', 'emailit-integration');
            default:
                $safe_body = sanitize_text_field(substr($response_body, 0, 200));
                return __('Unknown error', 'emailit-integration') . ': ' . $safe_body;
        }
    }

    /**
     * Get common opt-in meta keys from popular email marketing plugins
     */
    private function get_common_optin_meta_keys() {
        $common_keys = array(
            // Mailchimp for WooCommerce
            '_mailchimp_opt_in',
            'mailchimp_woocommerce_is_subscribed',
            // Klaviyo
            '_klaviyo_subscribe',
            '_klaviyo_opt_in',
            // Constant Contact
            '_constant_contact_opt_in',
            // AWeber
            '_aweber_opt_in',
            // GetResponse
            '_getresponse_opt_in',
            // ConvertKit
            '_convertkit_opt_in',
            // Generic/common patterns
            '_newsletter_subscribe',
            '_subscribe_to_newsletter',
            '_email_subscribe',
            '_opt_in',
            '_mailing_list',
        );

        // Add custom meta key if specified
        $custom_key = get_option('emailit_wc_custom_optin_meta_key', '');
        if (!empty($custom_key)) {
            $common_keys[] = sanitize_text_field($custom_key);
        }

        return $common_keys;
    }

    /**
     * Check if an order has opt-in from any previous plugin
     */
    private function order_has_previous_optin($order) {
        if (!is_a($order, 'WC_Order')) {
            return false;
        }

        $meta_keys = $this->get_common_optin_meta_keys();

        foreach ($meta_keys as $meta_key) {
            $value = $order->get_meta($meta_key);

            if (!empty($value)) {
                if ('yes' === $value || '1' === $value || 1 === $value || true === $value || 'true' === strtolower($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get orders that opted in through previous plugins but are not subscribed to EmailIt
     */
    private function get_pending_subscription_orders($limit = 50, $offset = 0) {
        $args = array(
            'limit'  => absint($limit),
            'offset' => absint($offset),
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
            'return' => 'ids',
        );

        $orders = wc_get_orders($args);
        $pending_orders = array();

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // Check if already successfully subscribed to EmailIt
            $subscribed = $order->get_meta('_emailit_subscribed');
            if ('yes' === $subscribed) {
                continue;
            }

            // Verify email exists and is valid
            $email = $order->get_billing_email();
            if (empty($email) || !is_email($email)) {
                continue;
            }

            // Check if they opted in through this plugin OR previous plugins
            $emailit_optin = $order->get_meta('_emailit_subscribe');
            $previous_optin = $this->order_has_previous_optin($order);

            if ($emailit_optin || $previous_optin) {
                $pending_orders[] = $order_id;
            }
        }

        $has_more = count($orders) === $limit;

        return array(
            'orders' => $pending_orders,
            'has_more' => $has_more,
            'scanned' => count($orders),
        );
    }

    /**
     * Get count of pending subscription orders (batched scanning)
     */
    private function get_pending_subscription_count_batch($limit = 100, $offset = 0) {
        $args = array(
            'limit'  => absint($limit),
            'offset' => absint($offset),
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
            'return' => 'ids',
        );

        $orders = wc_get_orders($args);
        $count = 0;

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $subscribed = $order->get_meta('_emailit_subscribed');
            if ('yes' === $subscribed) {
                continue;
            }

            $email = $order->get_billing_email();
            if (empty($email) || !is_email($email)) {
                continue;
            }

            $emailit_optin = $order->get_meta('_emailit_subscribe');
            $previous_optin = $this->order_has_previous_optin($order);

            if ($emailit_optin || $previous_optin) {
                $count++;
            }
        }

        $has_more = count($orders) === $limit;

        return array(
            'count' => $count,
            'has_more' => $has_more,
            'scanned' => count($orders),
        );
    }

    /**
     * AJAX handler: Get pending subscription count (batched scanning)
     */
    public function ajax_get_pending_count() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        $api_key = get_option('emailit_api_key', '');
        $audience_id = get_option('emailit_wc_audience_id', '');

        if (empty($api_key) || empty($audience_id)) {
            wp_send_json_error(array('message' => __('API key or Audience ID not configured.', 'emailit-integration')));
        }

        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $accumulated_count = isset($_POST['accumulated_count']) ? absint($_POST['accumulated_count']) : 0;
        $batch_size = 100;

        $result = $this->get_pending_subscription_count_batch($batch_size, $offset);

        $accumulated_count += $result['count'];
        $new_offset = $offset + $batch_size;

        if ($result['has_more']) {
            wp_send_json_success(array(
                'completed' => false,
                'offset' => $new_offset,
                'accumulated_count' => $accumulated_count,
                'scanned' => $result['scanned'],
                'message' => sprintf(
                    __('Scanning orders... Found %d pending subscriptions so far (scanned %d orders).', 'emailit-integration'),
                    $accumulated_count,
                    $new_offset
                ),
            ));
        } else {
            wp_send_json_success(array(
                'completed' => true,
                'count' => $accumulated_count,
                'scanned' => $result['scanned'],
                'message' => sprintf(
                    _n(
                        'Scan complete! Found %d order with pending subscription.',
                        'Scan complete! Found %d orders with pending subscriptions.',
                        $accumulated_count,
                        'emailit-integration'
                    ),
                    $accumulated_count
                ),
            ));
        }
    }

    /**
     * AJAX handler: Process pending orders in batches
     */
    public function ajax_process_pending_orders() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        $api_key = get_option('emailit_api_key', '');
        $audience_id = get_option('emailit_wc_audience_id', '');

        if (empty($api_key) || empty($audience_id)) {
            wp_send_json_error(array('message' => __('API key or Audience ID not configured.', 'emailit-integration')));
        }

        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $scan_batch_size = 50;
        $processed = isset($_POST['processed']) ? absint($_POST['processed']) : 0;
        $success_count = isset($_POST['success_count']) ? absint($_POST['success_count']) : 0;
        $error_count = isset($_POST['error_count']) ? absint($_POST['error_count']) : 0;
        $total_count = isset($_POST['total_count']) ? absint($_POST['total_count']) : 0;

        $result = $this->get_pending_subscription_orders($scan_batch_size, $offset);
        $pending_orders = $result['orders'];
        $has_more_orders = $result['has_more'];

        if (!$has_more_orders && empty($pending_orders)) {
            wp_send_json_success(array(
                'completed' => true,
                'processed' => $processed,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'message' => sprintf(
                    __('Processing complete! Processed %d orders: %d successful, %d errors.', 'emailit-integration'),
                    $processed,
                    $success_count,
                    $error_count
                ),
            ));
        }

        $remaining_orders = get_transient('emailit_wc_remaining_orders_' . get_current_user_id());
        if (false !== $remaining_orders && is_array($remaining_orders) && !empty($remaining_orders)) {
            $pending_orders = array_merge($remaining_orders, $pending_orders);
            delete_transient('emailit_wc_remaining_orders_' . get_current_user_id());
        }

        $batch_success = 0;
        $batch_errors = 0;

        $max_orders_per_batch = 10;
        $orders_to_process = array_slice($pending_orders, 0, $max_orders_per_batch);
        $remaining_after_batch = array_slice($pending_orders, $max_orders_per_batch);

        foreach ($orders_to_process as $order_id) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(30);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                $batch_errors++;
                $processed++;
                continue;
            }

            $email = $order->get_billing_email();
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();

            $result_subscribe = $this->subscribe_to_emailit($api_key, $audience_id, $email, $first_name, $last_name, $order);

            if ($result_subscribe) {
                $batch_success++;
            } else {
                $batch_errors++;
            }

            $processed++;

            usleep(100000);
        }

        $success_count += $batch_success;
        $error_count += $batch_errors;

        if (!empty($remaining_after_batch)) {
            set_transient('emailit_wc_remaining_orders_' . get_current_user_id(), $remaining_after_batch, 5 * MINUTE_IN_SECONDS);
        }

        if (!empty($remaining_after_batch)) {
            $new_offset = $offset;
            $completed = false;
        } else {
            $new_offset = $offset + $scan_batch_size;
            $completed = !$has_more_orders;
        }

        wp_send_json_success(array(
            'completed' => $completed,
            'offset' => $new_offset,
            'processed' => $processed,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total_count' => $total_count,
            'batch_success' => $batch_success,
            'batch_errors' => $batch_errors,
            'scanned' => $result['scanned'],
            'message' => $completed
                ? sprintf(
                    __('Processing complete! Processed %d orders: %d successful, %d errors.', 'emailit-integration'),
                    $processed,
                    $success_count,
                    $error_count
                )
                : sprintf(
                    __('Processed %d of %d orders: %d successful, %d errors. Continuing...', 'emailit-integration'),
                    $processed,
                    $total_count > 0 ? $total_count : '?',
                    $success_count,
                    $error_count
                ),
        ));
    }

    /**
     * AJAX handler: Store total count for processing
     */
    public function ajax_store_total_count() {
        check_ajax_referer('emailit_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'emailit-integration')));
        }

        $total_count = isset($_POST['total_count']) ? absint($_POST['total_count']) : 0;

        set_transient('emailit_wc_total_pending_count', $total_count, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'total_count' => $total_count,
            'message' => __('Total count stored.', 'emailit-integration'),
        ));
    }
}

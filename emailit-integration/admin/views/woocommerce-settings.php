<?php
/**
 * WooCommerce Settings Page Template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
$woocommerce_active = class_exists('WooCommerce');
$wc_enabled = get_option('emailit_wc_enabled', false);
$wc_audience_id = get_option('emailit_wc_audience_id', '');
$wc_checkbox_label = get_option('emailit_wc_checkbox_label', __('Subscribe to our newsletter', 'emailit-integration'));
$wc_checkbox_default = get_option('emailit_wc_checkbox_default', false);
$wc_custom_optin_meta_key = get_option('emailit_wc_custom_optin_meta_key', '');
?>

<div class="emailit-woocommerce-settings">
    <h2><?php esc_html_e('WooCommerce Integration', 'emailit-integration'); ?></h2>
    
    <!-- Feature Toggle Section -->
    <div class="emailit-wc-toggle-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">
        <div style="display: flex; align-items: flex-start; gap: 15px;">
            <div style="flex: 1;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px;">
                    <?php esc_html_e('Build Your Subscriber List with WooCommerce', 'emailit-integration'); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: #666; font-size: 14px; line-height: 1.6;">
                    <?php esc_html_e('Did you want to use Emailit to build your subscriber list? You can add WooCommerce purchasers to an Emailit audience by enabling this feature.', 'emailit-integration'); ?>
                </p>
                
                <?php if (!$woocommerce_active): ?>
                    <div class="notice notice-warning inline" style="margin: 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                        <p style="margin: 0;">
                            <strong><?php esc_html_e('WooCommerce Required:', 'emailit-integration'); ?></strong>
                            <?php esc_html_e('WooCommerce must be installed and active to use this feature.', 'emailit-integration'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="flex-shrink: 0;">
                <label class="emailit-toggle-switch" style="display: inline-block; position: relative; width: 60px; height: 34px;">
                    <input 
                        type="checkbox" 
                        id="emailit_wc_enabled" 
                        name="emailit_wc_enabled" 
                        value="1"
                        <?php checked($wc_enabled, true); ?>
                        <?php disabled(!$woocommerce_active); ?>
                        style="opacity: 0; width: 0; height: 0;"
                    >
                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;">
                        <span style="position: absolute; content: ''; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <!-- WooCommerce Settings (shown/hidden based on toggle) -->
    <div id="emailit-wc-settings-container" style="<?php echo $wc_enabled && $woocommerce_active ? '' : 'display: none;'; ?>">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="emailit_wc_audience_id"><?php esc_html_e('Audience ID', 'emailit-integration'); ?></label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="emailit_wc_audience_id" 
                        name="emailit_wc_audience_id" 
                        value="<?php echo esc_attr($wc_audience_id); ?>" 
                        class="regular-text"
                        placeholder="<?php esc_attr_e('Enter your Emailit audience ID', 'emailit-integration'); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('The ID of the Emailit audience where subscribers will be added.', 'emailit-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="emailit_wc_checkbox_label"><?php esc_html_e('Checkbox Label', 'emailit-integration'); ?></label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="emailit_wc_checkbox_label" 
                        name="emailit_wc_checkbox_label" 
                        value="<?php echo esc_attr($wc_checkbox_label); ?>" 
                        class="regular-text"
                    />
                    <p class="description">
                        <?php esc_html_e('The label text displayed next to the opt-in checkbox on the checkout page.', 'emailit-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="emailit_wc_checkbox_default"><?php esc_html_e('Checked by Default', 'emailit-integration'); ?></label>
                </th>
                <td>
                    <input 
                        type="checkbox" 
                        id="emailit_wc_checkbox_default" 
                        name="emailit_wc_checkbox_default" 
                        value="1"
                        <?php checked($wc_checkbox_default, true); ?>
                    />
                    <label for="emailit_wc_checkbox_default">
                        <?php esc_html_e('Check this box to have the opt-in checkbox checked by default.', 'emailit-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="emailit_wc_custom_optin_meta_key"><?php esc_html_e('Custom Opt-in Meta Key', 'emailit-integration'); ?></label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="emailit_wc_custom_optin_meta_key" 
                        name="emailit_wc_custom_optin_meta_key" 
                        value="<?php echo esc_attr($wc_custom_optin_meta_key); ?>" 
                        class="regular-text"
                        placeholder="<?php esc_attr_e('e.g., _mailchimp_opt_in, _klaviyo_subscribe', 'emailit-integration'); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Optional: Enter a custom order meta key from a previous plugin to identify customers who opted in. Leave empty to use common meta keys (Mailchimp, Klaviyo, etc.).', 'emailit-integration'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <hr style="margin: 30px 0;">

        <!-- Migration Tool Section -->
        <?php if ($wc_enabled && $woocommerce_active): ?>
            <h3><?php esc_html_e('Migrate Previous Subscribers', 'emailit-integration'); ?></h3>
            <p>
                <?php esc_html_e('Find customers who subscribed through previous plugins/tools and subscribe them to Emailit. This will check for common opt-in meta keys from Mailchimp, Klaviyo, and other popular email marketing plugins.', 'emailit-integration'); ?>
            </p>
            <div id="emailit-wc-sync-section">
                <p>
                    <button type="button" id="emailit-wc-check-pending" class="button">
                        <?php esc_html_e('Check for Pending Subscriptions', 'emailit-integration'); ?>
                    </button>
                </p>
                <div id="emailit-wc-sync-status" style="display: none;">
                    <p id="emailit-wc-pending-count"></p>
                    <p>
                        <button type="button" id="emailit-wc-process-pending" class="button button-primary" style="display: none;">
                            <?php esc_html_e('Process Pending Subscriptions', 'emailit-integration'); ?>
                        </button>
                    </p>
                    <div id="emailit-wc-progress" style="display: none; margin-top: 15px;">
                        <div style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; border-radius: 3px;">
                            <div id="emailit-wc-progress-bar" style="background: #2271b1; height: 20px; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <p id="emailit-wc-progress-text" style="margin-top: 10px;"></p>
                    </div>
                    <div id="emailit-wc-results" style="margin-top: 15px;"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle settings visibility based on checkbox state
    function toggleWooCommerceSettings() {
        var enabled = $('#emailit_wc_enabled').is(':checked');
        var woocommerceActive = <?php echo $woocommerce_active ? 'true' : 'false'; ?>;
        
        if (enabled && woocommerceActive) {
            $('#emailit-wc-settings-container').slideDown(300);
        } else {
            $('#emailit-wc-settings-container').slideUp(300);
        }
    }
    
    // Initial state
    toggleWooCommerceSettings();
    
    // Toggle on change
    $('#emailit_wc_enabled').on('change', function() {
        toggleWooCommerceSettings();
    });
    
    // Toggle switch styling
    $('.emailit-toggle-switch input').on('change', function() {
        var $slider = $(this).siblings('.slider');
        if ($(this).is(':checked')) {
            $slider.css('background-color', '#2271b1');
            $slider.find('span').css('transform', 'translateX(26px)');
        } else {
            $slider.css('background-color', '#ccc');
            $slider.find('span').css('transform', 'translateX(0)');
        }
    });
    
    // Set initial toggle state
    if ($('#emailit_wc_enabled').is(':checked')) {
        $('.emailit-toggle-switch .slider').css('background-color', '#2271b1');
        $('.emailit-toggle-switch .slider span').css('transform', 'translateX(26px)');
    }
});
</script>

<style>
.emailit-toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.emailit-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.emailit-toggle-switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.emailit-toggle-switch .slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.emailit-toggle-switch input:checked + .slider {
    background-color: #2271b1;
}

.emailit-toggle-switch input:checked + .slider:before {
    transform: translateX(26px);
}

.emailit-toggle-switch input:disabled + .slider {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>


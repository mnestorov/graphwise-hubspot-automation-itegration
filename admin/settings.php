<?php
/**
 * Graphwise Integration Plugin
 * 
 * This plugin integrates with Graphwise to handle course completion events and user data.
 * It provides a settings page to configure the API token and custom properties.
 *
 * @package GraphwiseIntegration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Adds a settings page to the WordPress admin menu.
 */
function graphwise_add_admin_menu() {
    add_options_page(
        'Graphwise Integration',
        'Graphwise Integration',
        'manage_options',
        'graphwise-integration',
        'graphwise_render_settings_page'
    );
}
add_action('admin_menu', 'graphwise_add_admin_menu');

/**
 * Registers the plugin settings.
 */
function graphwise_register_settings() {
    register_setting('graphwise_settings_group', 'graphwise_api_token');
    register_setting('graphwise_settings_group', 'graphwise_property_course_completed');
    register_setting('graphwise_settings_group', 'graphwise_property_completed_at');
}
add_action('admin_init', 'graphwise_register_settings');

/**
 * Renders the settings page.
 */
function graphwise_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Graphwise Integration | Settings', 'graphwise-integration'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('graphwise_settings_group'); ?>
            <?php do_settings_sections('graphwise_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">HubSpot API Token</th>
                    <td><input type="text" name="graphwise_api_token" value="<?php echo esc_attr(get_option('graphwise_api_token')); ?>" size="60" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Custom Property: Course Completed</th>
                    <td><input type="text" name="graphwise_property_course_completed" value="<?php echo esc_attr(get_option('graphwise_property_course_completed', 'course_completed')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Custom Property: Completed At</th>
                    <td><input type="text" name="graphwise_property_completed_at" value="<?php echo esc_attr(get_option('graphwise_property_completed_at', 'completed_at')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

<?php
/**
 * Plugin Name: Graphwise Integration
 * Plugin URI:  https://www.github.com/mnestorov/graphwise-integration
 * Description: Handles form redirect personalization, category tracking, and course completion webhook integration.
 * Version:     1.0
 * Author:      Martin Nestorov
 * Author URI:  https://www.github.com/mnestorov
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: graphwise-integration
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('GRAPHWISE_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once GRAPHWISE_PLUGIN_PATH . 'admin/settings.php';
require_once GRAPHWISE_PLUGIN_PATH . 'inc/thank-you.php';
require_once GRAPHWISE_PLUGIN_PATH . 'inc/category-tracker.php';
require_once GRAPHWISE_PLUGIN_PATH . 'inc/webhook-handler.php';
require_once GRAPHWISE_PLUGIN_PATH . 'inc/hubspot-embed.php';

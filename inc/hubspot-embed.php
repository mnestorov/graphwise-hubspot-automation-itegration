<?php
/**
 * HubSpot Embed Code Metabox
 * 
 * This file adds a metabox to the post edit screen for adding HubSpot embed code.
 * It also saves the embed code as post meta and displays it in the post content.
 * 
 * @package GraphwiseIntegration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Adds a metabox to the post edit screen for HubSpot embed code.
 */
function graphwise_add_embed_metabox() {
    add_meta_box('graphwise_hubspot_embed', 'HubSpot Embed Code', 'graphwise_render_embed_metabox', ['page', 'post'], 'normal', 'high');
}
add_action('add_meta_boxes', 'graphwise_add_embed_metabox');

/**
 * Renders the metabox for HubSpot embed code.
 *
 * @param WP_Post $post The current post object.
 */
function graphwise_render_embed_metabox($post) {
    $value = get_post_meta($post->ID, '_graphwise_hubspot_embed', true);
    echo '<label for="graphwise_hubspot_embed">Paste your HubSpot embed code:</label>';
    echo '<textarea name="graphwise_hubspot_embed" style="width:100%;min-height:150px;">' . esc_textarea($value) . '</textarea>';
    wp_nonce_field('graphwise_save_embed_code', 'graphwise_embed_nonce');
}

/**
 * Saves the HubSpot embed code when the post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function graphwise_save_embed_metabox($post_id) {
    if (!isset($_POST['graphwise_embed_nonce']) || !wp_verify_nonce($_POST['graphwise_embed_nonce'], 'graphwise_save_embed_code')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['graphwise_hubspot_embed'])) update_post_meta($post_id, '_graphwise_hubspot_embed', $_POST['graphwise_hubspot_embed']);
}
add_action('save_post', 'graphwise_save_embed_metabox');

/**
 * Adds the HubSpot embed code to the content of the post.
 *
 * @param string $content The post content.
 * @return string The modified post content.
 */
function graphwise_add_embed_to_content($content) {
    if (is_singular() && in_the_loop() && is_main_query()) {
        $embed = get_post_meta(get_the_ID(), '_graphwise_hubspot_embed', true);
        if ($embed) $content .= '<div class="graphwise-hubspot-embed">' . $embed . '</div>';
    }
    return $content;
}
add_filter('the_content', 'graphwise_add_embed_to_content');

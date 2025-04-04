<?php
/**
 * Category Tracker
 *
 * This file contains the code for tracking category completions and sending data to HubSpot.
 * It includes functions to handle the tracking of categories and sending data to HubSpot.
 *
 * @package GraphwiseIntegration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Enqueues the category tracker script.
 *
 * This function checks if the current page is a category archive and enqueues a script
 * that tracks the category and sends it to HubSpot.
 * 
 * @return void
 */
function graphwise_enqueue_category_tracker() {
    if (is_category()) {
        wp_enqueue_script('graphwise-category-tracker', '', [], null, true);
        wp_add_inline_script('graphwise-category-tracker', "
            document.addEventListener('DOMContentLoaded', function() {
                const email = sessionStorage.getItem('graphwise_email');
                const category = '" . single_cat_title('', false) . "';

                if (!email || !category) return;

                const data = new FormData();
                data.append('action', 'graphwise_track_category');
                data.append('email', email);
                data.append('category', category.toLowerCase().replace(/[^a-z0-9]/g, '_'));

                fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: data
                })
                .then(res => res.json())
                .then(res => console.log('Category tracked:', res))
                .catch(err => console.error('Graphwise track error:', err));
            });
        ");
    }
}
add_action('wp_enqueue_scripts', 'graphwise_enqueue_category_tracker');

/**
 * Handles the AJAX request to track category completion.
 * 
 * This function is triggered by an AJAX request and updates the user's category completion
 * status in HubSpot. It searches for the user by email and updates the custom property
 * corresponding to the category.
 * 
 * @return void
 */
function graphwise_track_category_ajax() {
    $email    = sanitize_email($_POST['email'] ?? '');
    $category = sanitize_key($_POST['category'] ?? '');

    if (!$email || !$category) {
        wp_send_json_error(['message' => 'Missing data']);
    }

    $token = get_option('graphwise_api_token');
    if (!$token) wp_send_json_error(['message' => 'Missing API token']);

    // Търсим контакта по имейл
    $search = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'email',
                    'operator' => 'EQ',
                    'value' => $email
                ]]
            ]],
            'properties' => ['email']
        ])
    ]);

    if (is_wp_error($search)) wp_send_json_error(['message' => 'Search error']);
    $body = json_decode(wp_remote_retrieve_body($search), true);
    $contact_id = $body['results'][0]['id'] ?? null;
    if (!$contact_id) wp_send_json_error(['message' => 'Contact not found']);

    $property_key = 'interest_' . $category;
    $update = wp_remote_patch("https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'properties' => [
                $property_key => '1'
            ]
        ])
    ]);

    if (is_wp_error($update)) wp_send_json_error(['message' => 'Update error']);
    wp_send_json_success(['message' => 'Category updated']);
}
add_action('wp_ajax_graphwise_track_category', 'graphwise_track_category_ajax');
add_action('wp_ajax_nopriv_graphwise_track_category', 'graphwise_track_category_ajax');

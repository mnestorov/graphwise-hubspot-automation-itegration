<?php
/**
 * Handles the webhook from Graphwise for course completion.
 * 
 * This plugin integrates with Graphwise to handle course completion events and user data.
 * It provides a webhook endpoint to receive course completion data and update user information in HubSpot.
 *
 * @package GraphwiseIntegration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Handles the webhook from Graphwise for course completion.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response The response object.
 */
function graphwise_handle_webhook(WP_REST_Request $request) {
    $data = $request->get_json_params();
    $email = sanitize_email($data['contact_email'] ?? '');
    $course_id = sanitize_text_field($data['course_id'] ?? '');
    $completed_at = sanitize_text_field($data['completed_at'] ?? '');
    if (empty($email) || empty($course_id)) return new WP_REST_Response(['error' => 'Missing parameters'], 400);

    $token = 'pat-eu1-a76dea28-3948-4dc1-bc7e-55bd8cd34df5';

    $search_response = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'body' => json_encode(['filterGroups' => [[
            'filters' => [['propertyName' => 'email', 'operator' => 'EQ', 'value' => $email]]
        ]], 'properties' => ['email']])
    ]);

    $contact_id = null;
    if (!is_wp_error($search_response)) {
        $search_body = json_decode(wp_remote_retrieve_body($search_response), true);
        $contact_id = $search_body['results'][0]['id'] ?? null;
    }

    $hubspot_data = ['properties' => [
        'email' => $email,
        'course_completed' => $course_id,
        'completed_at' => $completed_at
    ]];

    $hubspot_response = $contact_id ? wp_remote_patch("https://api.hubapi.com/crm/v3/objects/contacts/{$contact_id}", [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'body' => json_encode($hubspot_data)
    ]) : wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts', [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'body' => json_encode($hubspot_data)
    ]);

    $cert_response = wp_remote_post('https://cert-api.example.com/generate', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['email' => $email, 'course_id' => $course_id])
    ]);

    $cert_data = json_decode(wp_remote_retrieve_body($cert_response), true);
    return new WP_REST_Response([
        'status' => 'ok',
        'hubspot_status' => wp_remote_retrieve_response_code($hubspot_response),
        'certificate_url' => $cert_data['certificate_url'] ?? ''
    ]);
}

/**
 * Registers the webhook endpoint for Graphwise.
 */
function graphwise_register_webhook_endpoint() {
    register_rest_route('graphwise/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'graphwise_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'graphwise_register_webhook_endpoint');

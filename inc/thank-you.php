<?php
/**
 * Graphwise Integration Plugin
 * 
 * This plugin integrates with Graphwise to handle course completion events and user data.
 * It provides a shortcode to display a thank you message and fetch user data from HubSpot.
 *
 * @package GraphwiseIntegration
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('graphwise_thankyou_shortcode')) {
    /**
     * Shortcode to display a thank you message and fetch user data.
     *
     * @return string HTML output for the thank you message.
     */
    function graphwise_thankyou_shortcode() {
        ob_start(); ?>
        <div id="graphwise-thankyou"><p><?php esc_html_e('We’re checking your details…', 'graphwise-integration'); ?></p></div>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const email = sessionStorage.getItem("graphwise_email");
            if (!email) return;
            const data = new FormData();
            data.append("action", "graphwise_get_contact");
            data.append("email", email);
            fetch("/wp-admin/admin-ajax.php", {
                method: "POST",
                body: data
            })
            .then(res => res.json())
            .then(res => {
                const el = document.getElementById("graphwise-thankyou");
                if (res.success && res.data) {
                    const { firstname, lastname, email } = res.data;
                    el.innerHTML = `<h2>Thank you, ${firstname} ${lastname}!</h2><p>We’ve sent a confirmation to <strong>${email}</strong>.</p>`;
                } else {
                    el.innerHTML = "<p>Thank you! (No user data found)</p>";
                }
            })
            .catch(err => console.error("AJAX error", err));
        });
        </script>
        <?php return ob_get_clean();
    }
    add_shortcode('graphwise_thankyou', 'graphwise_thankyou_shortcode');
}

if (!function_exists('graphwise_get_contact_by_email')) {
    /**
     * Fetches contact information from HubSpot by email.
     */
    function graphwise_get_contact_by_email() {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) wp_send_json_error(['message' => 'Missing email']);
        $token = get_option('graphwise_api_token');
        if (empty($token)) wp_send_json_error(['message' => 'Missing API token']);
        $response = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
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
                'properties' => ['firstname', 'lastname', 'email']
            ]),
        ]);
        if (is_wp_error($response)) wp_send_json_error(['message' => 'HubSpot API error']);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $contact = $data['results'][0]['properties'] ?? [];
        !empty($contact) ? wp_send_json_success($contact) : wp_send_json_error(['message' => 'Contact not found']);
    }
    add_action('wp_ajax_graphwise_get_contact', 'graphwise_get_contact_by_email');
    add_action('wp_ajax_nopriv_graphwise_get_contact', 'graphwise_get_contact_by_email');
}
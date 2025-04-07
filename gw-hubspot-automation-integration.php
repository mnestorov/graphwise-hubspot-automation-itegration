<?php
/**
 * Plugin Name: Graphwise - HubSpot Automation Integration
 * Plugin URI:  https://github.com/mnestorov/graphwise-hubspot-automation-itegration
 * Description: Handles HubSpot form integration, category interest tracking, and course-completion certificate automation.
 * Version:     1.0
 * Author:      Martin Nestorov
 * Author URI:  https://github.com/mnestorov
 */

// -----------------------------------------
// 1) Footer Scripts (Category tracking)
// -----------------------------------------
if (!function_exists('graphwise_footer_scripts')) {
    /**
     * Enqueues the footer scripts for category tracking and Thank You page logic.
     *
     * @since 1.0.0
     * @return void
     */
    function graphwise_footer_scripts() {
        // Enqueue the footer script for category tracking and Thank You page logic
        wp_enqueue_script(
            'graphwise-footer-scripts',
            plugin_dir_url(__FILE__) . 'js/footer-scripts.js',
            [],
            '1.0',
            true
        );

        // Pass category slugs to the script if on a single post or category archive
        if (is_single() || is_category()) {
            $cats = [];
            if (is_single()) {
                $cats = get_the_category();
            } elseif (is_category()) {
                $cats = [get_queried_object()];
            }
            $slugs = array_map(fn($cat) => $cat->slug, $cats);

            wp_localize_script(
                'graphwise-footer-scripts',
                'graphwiseCategories',
                [
                    'currentCategories' => $slugs
                ]
            );
        }
    }
}
add_action('wp_footer', 'graphwise_footer_scripts');

// -----------------------------------------
// 1.1) Shortcode for Thank You Message Container
// -----------------------------------------
if (!function_exists('graphwise_thank_you_message')) {
    /**
     * Shortcode to output the Thank You message container.
     *
     * @since 1.0.0
     * @return string
     */
    function graphwise_thank_you_message() {
        $thank_you_page_slug = get_option('graphwise_thank_you_page_slug', 'thank-you');

        // Only output the container if we're on the Thank You page
        if (is_page($thank_you_page_slug)) {
            return '<div id="thank-you-message"></div>';
        }
        return '';
    }
}
add_shortcode('thank_you_message', 'graphwise_thank_you_message');

// ---------------------------------------------------------------------
// 2) HubSpot Form & Category Tracker JS (form logic)
// 
// Important: Not needed if the form was rendered by the official HubSpot plugin
// This is a custom implementation to inject category visit counters
// ---------------------------------------------------------------------
if (!function_exists('graphwise_hubspot_tracker_js')) {
    /**
     * Shortcode that renders the HubSpot form and injects category visit counters.
     *
     * @since 1.0.0
     * @return string
     */
    function graphwise_hubspot_tracker_js() {
        $portalId = esc_js(get_option('graphwise_hubspot_portal_id'));
        $formId   = esc_js(get_option('graphwise_hubspot_form_id'));

        if (empty($portalId) || empty($formId)) {
            return '<p>Error: HubSpot Portal ID and Form ID must be configured in Graphwise Settings.</p>';
        }

        // Enqueue the HubSpot forms script (v2.js)
        wp_enqueue_script(
            'hubspot-forms',
            '//js.hsforms.net/forms/v2.js',
            [],
            null,
            true
        );

        // Enqueue the custom HubSpot tracker script
        wp_enqueue_script(
            'graphwise-hubspot-tracker',
            plugin_dir_url(__FILE__) . 'js/hubspot-tracker.js',
            ['hubspot-forms'],
            '1.0',
            true
        );

        // Pass HubSpot settings to the script
        wp_localize_script(
            'graphwise-hubspot-tracker',
            'graphwiseHubSpotSettings',
            [
                'portalId' => $portalId,
                'formId'   => $formId
            ]
        );

        ob_start(); ?>
        <div id="hubspot-form"></div>
        <?php return ob_get_clean();
    }
}
add_shortcode('hubspot_tracker_js', 'graphwise_hubspot_tracker_js');

// --------------------------------------------------
// 3) REST route for Academy Webhook
// --------------------------------------------------
if (!function_exists('graphwise_register_rest_route')) {
    /**
     * Registers REST API routes for handling course completion and category updates.
     *
     * @since 1.0.0
     * @return void
     */
    function graphwise_register_rest_route() {
        register_rest_route('graphwise/v1', '/course-complete', [
            'methods'             => 'POST',
            'callback'            => 'graphwise_handle_webhook',
            'permission_callback' => function(WP_REST_Request $request) {
                $api_key = $request->get_header('X-API-Key');
                $stored_api_key = get_option('graphwise_webhook_api_key', '');
                return $api_key && $api_key === $stored_api_key;
            }
        ]);

        // New endpoint for updating categories
        register_rest_route('graphwise/v1', '/update-categories', [
            'methods'             => 'POST',
            'callback'            => 'graphwise_update_categories',
            'permission_callback' => function($request) {
                $nonce = $request->get_header('X-WP-Nonce');
                if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                    return new WP_Error('rest_forbidden', 'Invalid nonce', ['status' => 403]);
                }
                return true;
            },
        ]);
    }
}
add_action('rest_api_init', 'graphwise_register_rest_route');

if (!function_exists('graphwise_handle_webhook')) {
    /**
     * Handles the webhook from the academy and updates HubSpot contact properties.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    function graphwise_handle_webhook($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $course = sanitize_text_field($params['course_name'] ?? '');

        // Log the incoming request
        error_log('Graphwise Webhook: Received request - Email: ' . $email . ', Course: ' . $course);

        // Validate required fields
        if (empty($email) || empty($course)) {
            error_log('Graphwise Webhook Error: Missing email or course_name in request');
            return new WP_Error('invalid_request', 'Missing email or course_name', ['status' => 400]);
        }

        $token = get_option('graphwise_hubspot_token');
        if (empty($token)) {
            error_log('Graphwise Webhook Error: HubSpot token is not configured');
            return new WP_Error('missing_token', 'HubSpot token is not configured', ['status' => 500]);
        }

        // Log the token (partially masked for security)
        $token_preview = substr($token, 0, 5) . '...' . substr($token, -5);
        error_log('Graphwise Webhook: Using HubSpot token - ' . $token_preview);

        // Search contact by email
        $search = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'filterGroups' => [[
                    'filters'  => [[
                        'propertyName' => 'email',
                        'operator'     => 'EQ',
                        'value'        => $email
                    ]]
                ]]
            ])
        ]);

        // Check for errors in the search request
        if (is_wp_error($search)) {
            error_log('Graphwise Webhook Error: HubSpot search request failed - ' . $search->get_error_message());
            return new WP_Error('hubspot_search_failed', 'Failed to search for contact in HubSpot', ['status' => 500]);
        }

        $search_response_code = wp_remote_retrieve_response_code($search);
        $search_body = wp_remote_retrieve_body($search);
        $res = json_decode($search_body, true);

        // Log the search response
        error_log('Graphwise Webhook: HubSpot search response - Status: ' . $search_response_code . ', Body: ' . $search_body);

        if ($search_response_code !== 200) {
            error_log("Graphwise Webhook Error: HubSpot search failed with status $search_response_code - $search_body");
            return new WP_Error('hubspot_search_failed', 'HubSpot search failed', ['status' => $search_response_code]);
        }

        $id = $res['results'][0]['id'] ?? null;
        if (!$id) {
            error_log("Graphwise Webhook Error: Contact not found in HubSpot for email: $email");
            return new WP_Error('contact_not_found', "Contact not found in HubSpot for email: $email", ['status' => 404]);
        }

        // Log the contact ID
        error_log('Graphwise Webhook: Found contact in HubSpot - Contact ID: ' . $id);

        // Update contact with course property
        $update = wp_remote_request("https://api.hubapi.com/crm/v3/objects/contacts/$id", [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'properties' => [ 'latest_completed_course' => $course ]
            ])
        ]);

        // Check for errors in the update request
        if (is_wp_error($update)) {
            error_log('Graphwise Webhook Error: HubSpot update request failed - ' . $update->get_error_message());
            return new WP_Error('hubspot_update_failed', 'Failed to update contact in HubSpot', ['status' => 500]);
        }

        $update_response_code = wp_remote_retrieve_response_code($update);
        $update_body = wp_remote_retrieve_body($update);

        // Log the update response
        error_log('Graphwise Webhook: HubSpot update response - Status: ' . $update_response_code . ', Body: ' . $update_body);

        if ($update_response_code !== 200) {
            error_log("Graphwise Webhook Error: HubSpot update failed with status $update_response_code - $update_body");
            return new WP_Error('hubspot_update_failed', 'HubSpot update failed', ['status' => $update_response_code]);
        }

        // Log successful update
        error_log('Graphwise Webhook: Successfully updated contact in HubSpot - Email: ' . $email . ', latest_completed_course: ' . $course);

        // Call certificate generation API
        $certificate_response = wp_remote_post('https://certificate-api.com/generate', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([ 'email' => $email, 'course' => $course ])
        ]);

        if (is_wp_error($certificate_response)) {
            error_log('Graphwise Webhook Warning: Certificate API call failed - ' . $certificate_response->get_error_message());
            // Not returning an error here since this is a mock API and not critical
        }

        return rest_ensure_response(['status' => 'success']);
    }
}

if (!function_exists('graphwise_update_categories')) {
    /**
     * Updates HubSpot contact properties based on category visits.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    function graphwise_update_categories($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $categories = $params['categories'] ?? [];

        if (empty($email)) {
            return new WP_REST_Response(['error' => 'No email provided'], 400);
        }

        $token = get_option('graphwise_hubspot_token');
        if (empty($token)) {
            return new WP_REST_Response(['error' => 'HubSpot token not configured'], 500);
        }

        // 1) Find contact by email
        $search = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'filterGroups' => [[
                    'filters'  => [[
                        'propertyName' => 'email',
                        'operator'     => 'EQ',
                        'value'        => $email
                    ]]
                ]]
            ])
        ]);

        $response = wp_remote_retrieve_response_code($search);
        $body = wp_remote_retrieve_body($search);
        if ($response !== 200) {
            return new WP_REST_Response(['error' => 'Failed to search for contact', 'details' => $body], $response);
        }

        $res = json_decode($body, true);
        $id = $res['results'][0]['id'] ?? null;
        if (!$id) {
            return new WP_REST_Response(['error' => 'Contact not found for email: ' . $email], 404);
        }

        // 2) Prepare the properties to update
        $propertiesToPatch = [];
        foreach ($categories as $cat => $count) {
            $propertiesToPatch["interest_$cat"] = $count;
        }

        // 3) Patch the contact
        $patch = wp_remote_request("https://api.hubapi.com/crm/v3/objects/contacts/$id", [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'properties' => $propertiesToPatch
            ])
        ]);

        $patchResponse = wp_remote_retrieve_response_code($patch);
        $patchBody = wp_remote_retrieve_body($patch);
        if ($patchResponse !== 200) {
            return new WP_REST_Response(['error' => 'Failed to update contact', 'details' => $patchBody], $patchResponse);
        }

        return new WP_REST_Response(['status' => 'success'], 200);
    }
}

// -----------------------------------
// 4) Admin Settings Page
// -----------------------------------
if (!function_exists('graphwise_admin_menu')) {
	/**
	 * Adds a plugin settings page under Settings > Graphwise.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_admin_menu() {
        add_options_page('Graphwise Settings', 'HubSpot (Graphwise)', 'manage_options', 'graphwise-settings', 'graphwise_settings_page');
    }
}
add_action('admin_menu', 'graphwise_admin_menu');

if (!function_exists('graphwise_settings_page')) {
	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HubSpot (Graphwise) | Settings', 'graphwise-hubspot-automation-integration'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('graphwise_settings');
                do_settings_sections('graphwise-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

if (!function_exists('graphwise_admin_init')) {
	/**
	 * Registers settings fields for HubSpot API credentials.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_admin_init() {
        register_setting('graphwise_settings', 'graphwise_hubspot_token');
        register_setting('graphwise_settings', 'graphwise_hubspot_portal_id');
        register_setting('graphwise_settings', 'graphwise_hubspot_form_id');
        register_setting('graphwise_settings', 'graphwise_thank_you_page_slug', [
            'sanitize_callback' => 'sanitize_title'
        ]);
        register_setting('graphwise_settings', 'graphwise_webhook_api_key');

        add_settings_section(
			'default', 
			'HubSpot Settings', 
			null, 
			'graphwise-settings'
		);

        add_settings_field(
			'graphwise_hubspot_token', 
			'HubSpot Token', 
			'graphwise_render_token_field_cb', 
			'graphwise-settings', 
			'default'
		);
		
        add_settings_field(
			'graphwise_hubspot_portal_id', 
			'HubSpot Portal ID', 
			'graphwise_render_portal_id_field_cb', 
			'graphwise-settings', 
			'default'
		);
		
		add_settings_field(
			'graphwise_hubspot_form_id', 
			'HubSpot Form ID', 
			'graphwise_render_form_id_field_cb', 
			'graphwise-settings', 
			'default'
		);

        add_settings_field(
            'graphwise_thank_you_page_slug',
            'Thank You Page Slug',
            'graphwise_render_thank_you_page_slug_field_cb',
            'graphwise-settings',
            'default'
        );

        add_settings_field(
            'graphwise_webhook_api_key',
            'Webhook API Key',
            'graphwise_render_webhook_api_key_field_cb',
            'graphwise-settings',
            'default'
        );
    }
}
add_action('admin_init', 'graphwise_admin_init');

if (!function_exists('graphwise_render_token_field_cb')) {
	/**
	 * Renders the HubSpot Token input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_token_field_cb() {
        echo '<input type="text" name="graphwise_hubspot_token" value="' . esc_attr(get_option('graphwise_hubspot_token')) . '" size="50">';
    }
}

if (!function_exists('graphwise_render_portal_id_field_cb')) {
	/**
	 * Renders the HubSpot Portal ID input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_portal_id_field_cb() {
        echo '<input type="text" name="graphwise_hubspot_portal_id" value="' . esc_attr(get_option('graphwise_hubspot_portal_id')) . '" size="50">';
    }
}

if (!function_exists('graphwise_render_form_id_field_cb')) {
	/**
	 * Renders the HubSpot Form ID input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_form_id_field_cb() {
        echo '<input type="text" name="graphwise_hubspot_form_id" value="' . esc_attr(get_option('graphwise_hubspot_form_id')) . '" size="50">';
    }
}

if (!function_exists('graphwise_render_thank_you_page_slug_field_cb')) {
    /**
     * Renders the Thank You Page Slug input field.
     *
     * @since 1.0.0
     * @return void
     */
    function graphwise_render_thank_you_page_slug_field_cb() {
        $value = get_option('graphwise_thank_you_page_slug', 'thank-you'); // Default to 'thank-you'
        echo '<input type="text" name="graphwise_thank_you_page_slug" value="' . esc_attr($value) . '" size="50">';
        echo '<p class="description">Enter the slug of the Thank You page (e.g., "thank-you"). This is the page where users are redirected after form submission.</p>';
    }
}

if (!function_exists('graphwise_render_webhook_api_key_field_cb')) {
    /**
     * Renders the Webhook API Key input field.
     *
     * @since 1.0.0
     * @return void
     */
    function graphwise_render_webhook_api_key_field_cb() {
        $value = get_option('graphwise_webhook_api_key', '');
        echo '<input type="text" name="graphwise_webhook_api_key" value="' . esc_attr($value) . '" size="50">';
        echo '<p class="description">Enter an API key for securing the course completion webhook. This key must be included in the <code>X-API-Key</code> header of webhook requests.</p>';
    }
}
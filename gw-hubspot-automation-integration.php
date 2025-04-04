<?php
/**
 * Plugin Name: Graphwise - HubSpot Automation Integration
 * Plugin URI:  https://github.com/mnestorov/graphwise-hubspot-automation-itegration
 * Description: Handles HubSpot form integration, category interest tracking, and course-completion certificate automation.
 * Version: 	1.0
 * Author: 		Martin Nestorov
 * Author URI:  https://github.com/mnestorov
 */

if (!function_exists('graphwise_footer_scripts')) {
	/**
	 * Injects personalization and category tracking JavaScript on the frontend.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_footer_scripts() {
        if (is_page('thank-you')) {
            ?>
            <div id="thank-you-message"></div>
            <script>
                const data = sessionStorage.getItem("hubspot_submitted_data");
                if (data) {
                    const { firstName, lastName, email } = JSON.parse(data);
                    document.getElementById("thank-you-message").innerHTML = `
                        <h2>Thank you, ${firstName} ${lastName}!</h2>
                        <p>Confirmation sent to: <strong>${email}</strong>.</p>
                    `;
                    sessionStorage.removeItem("hubspot_submitted_data");
                }
            </script>
            <?php
        }

        if (is_single()) {
            $cats = get_the_category();
            $slugs = array_map(fn($cat) => $cat->slug, $cats);
            ?>
            <script>
                const visitedCategories = JSON.parse(localStorage.getItem('visited_categories') || '{}');
                const currentCategories = <?php echo json_encode($slugs); ?>;
                currentCategories.forEach(cat => {
                    visitedCategories[cat] = (visitedCategories[cat] || 0) + 1;
                });
                localStorage.setItem('visited_categories', JSON.stringify(visitedCategories));
            </script>
            <?php
        }
    }
}
add_action('wp_footer', 'graphwise_footer_scripts');

if (!function_exists('graphwise_hubspot_tracker_js')) {
	/**
	 * Shortcode that renders the HubSpot form and injects category visit counters.
	 *
	 * @since 1.0.0
	 * @return string
	 */
    function graphwise_hubspot_tracker_js() {
        $portalId = esc_js(get_option('graphwise_hubspot_portal_id'));
        $formId = esc_js(get_option('graphwise_hubspot_form_id'));

        ob_start(); ?>
        <script>
        hbspt.forms.create({
            region: "na1",
            portalId: "<?php echo $portalId; ?>",
            formId: "<?php echo $formId; ?>",
            onFormSubmit: function($form) {
                const data = {
                    firstName: $form.find("input[name='firstname']").val(),
                    lastName: $form.find("input[name='lastname']").val(),
                    email: $form.find("input[name='email']").val()
                };
                sessionStorage.setItem("hubspot_submitted_data", JSON.stringify(data));
            },
            onFormReady: function($form) {
                const visits = JSON.parse(localStorage.getItem('visited_categories') || '{}');
                for (const cat in visits) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'interest_' + cat;
                    input.value = visits[cat];
                    $form.append(input);
                }
            }
        });
        </script>
        <?php return ob_get_clean();
    }
}
add_shortcode('hubspot_tracker_js', 'graphwise_hubspot_tracker_js');

if (!function_exists('graphwise_register_rest_route')) {
	/**
	 * Registers the REST API endpoint for the academy webhook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_register_rest_route() {
        register_rest_route('graphwise/v1', '/course-complete', [
            'methods' => 'POST',
            'callback' => 'graphwise_handle_webhook',
            'permission_callback' => '__return_true',
        ]);
    }
}
add_action('rest_api_init', 'graphwise_register_rest_route');

if (!function_exists('graphwise_handle_webhook')) {
	/**
	 * Handles incoming course completion data, updates HubSpot, and calls certificate API.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
    function graphwise_handle_webhook($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email']);
        $course = sanitize_text_field($params['course_name']);

        $token = get_option('graphwise_hubspot_token');

        // Search contact by email
        $search = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts/search', [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'filterGroups' => [[
                    'filters' => [[
                        'propertyName' => 'email',
                        'operator' => 'EQ',
                        'value' => $email
                    ]]
                ]]
            ])
        ]);

        $res = json_decode(wp_remote_retrieve_body($search), true);
        $id = $res['results'][0]['id'] ?? null;

        if ($id) {
            // Update contact with course property
            wp_remote_request("https://api.hubapi.com/crm/v3/objects/contacts/$id", [
                'method' => 'PATCH',
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'properties' => [ 'latest_completed_course' => $course ]
                ])
            ]);
        }

        // Call certificate generation API
        wp_remote_post('https://certificate-api.com/generate', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([ 'email' => $email, 'course' => $course ])
        ]);

        return rest_ensure_response(['status' => 'success']);
    }
}

if (!function_exists('graphwise_admin_menu')) {
	/**
	 * Adds a plugin settings page under Settings > Graphwise.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_admin_menu() {
        add_options_page('Graphwise Settings', 'Graphwise', 'manage_options', 'graphwise-settings', 'graphwise_settings_page');
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
            <h1>Graphwise Plugin Settings</h1>
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

        add_settings_section(
			'default', 
			'HubSpot Settings', 
			null, 
			'graphwise-settings'
		);

        add_settings_field(
			'graphwise_hubspot_token', 
			'HubSpot Token', 
			'graphwise_render_token_field', 
			'graphwise-settings', 
			'default'
		);
		
		add_settings_field(
			'graphwise_hubspot_portal_id', 
			'HubSpot Portal ID', 
			'graphwise_render_portal_id_field', 
			'graphwise-settings', 
			'default'
		);
		
		add_settings_field(
			'graphwise_hubspot_form_id', 
			'HubSpot Form ID', 
			'graphwise_render_form_id_field', 
			'graphwise-settings', 
			'default'
		);
    }
}
add_action('admin_init', 'graphwise_admin_init');

if (!function_exists('graphwise_render_token_field')) {
	/**
	 * Renders the HubSpot Token input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_token_field() {
        echo '<input type="text" name="graphwise_hubspot_token" value="' . esc_attr(get_option('graphwise_hubspot_token')) . '" size="50">';
    }
}

if (!function_exists('graphwise_render_portal_id_field')) {
	/**
	 * Renders the HubSpot Portal ID input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_portal_id_field() {
        echo '<input type="text" name="graphwise_hubspot_portal_id" value="' . esc_attr(get_option('graphwise_hubspot_portal_id')) . '" size="50">';
    }
}

if (!function_exists('graphwise_render_form_id_field')) {
	/**
	 * Renders the HubSpot Form ID input field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
    function graphwise_render_form_id_field() {
        echo '<input type="text" name="graphwise_hubspot_form_id" value="' . esc_attr(get_option('graphwise_hubspot_form_id')) . '" size="50">';
    }
}
?>

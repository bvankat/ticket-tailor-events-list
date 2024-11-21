<?php
/**
 * Plugin Name: Ticket Tailor Events List
 * Description: Pulls event data from Ticket Tailor API and displays it on the front end via shortcode.
 * Version: 1.0
 * Author: Ben Vankat, Hanscom Park Studio
 */


// Add a shortcode to display events
add_shortcode('show_events', 'display_events');

// Add a settings field for the API key
 function event_api_register_settings() {
	 add_option('ticket_tailor_api_key', '');
	 register_setting('event_api_options_group', 'ticket_tailor_api_key', 'esc_attr');
 }
 add_action('admin_init', 'event_api_register_settings');
 
// Create settings page
function event_api_register_options_page() {
 add_options_page('HPS Ticket Tailor Plugin', 'HPS Ticket Tailor Plugin', 'manage_options', 'event-api-plugin', 'event_api_options_page');
}

add_action('admin_menu', 'event_api_register_options_page');
 
// Display the settings page
function event_api_options_page() {
?>
 <div>
	 <h2>Ticket Tailor API Plugin Settings</h2>
	 <form method="post" action="options.php">
		 <?php settings_fields('event_api_options_group'); ?>
		 <table>
			 <tr valign="top">
				 <th scope="row"><label for="ticket_tailor_api_key">Ticket Tailor API Key</label></th>
				 <td><input type="text" id="ticket_tailor_api_key" name="ticket_tailor_api_key" value="<?php echo get_option('ticket_tailor_api_key'); ?>" /></td>
			 </tr>
		 </table>
		 <?php submit_button(); ?>
	 </form>
 </div>
<?php
}


// Function to pull data from Ticket Tailor API and display events
function display_events() {
	$api_key = get_option('ticket_tailor_api_key');

	if (empty($api_key)) {
		return '<script>console.error("API key is not set.");</script>';
	}

	// Get timestamp of now
	$now_time = time();

	// Ticket Tailor API endpoint
	// Two options to pull events: event_series or events
	// Get all events (up to the limit) that where the start date is later than right now
	$api_url = 'https://api.tickettailor.com/v1/events?limit=8&start_at.gt=' . $now_time;

	$response = wp_remote_get($api_url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
			'Accept'        => 'application/json',
		),
	));

	if (is_wp_error($response)) {
		// Log the error to the browser console
		return '<script>console.error("Error fetching events: ' . esc_js($response->get_error_message()) . '");</script>';
	}

	// Log the HTTP status code and response in the browser console
	$status_code = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);
	$events = json_decode($body);
	$event_count = count($events->data);

	// Add console logging for debugging
	$output = '<script>';
	$output .= 'console.log("API Response Code: ' . esc_js($status_code) . '");';
	$output .= 'console.log("API Response Body: ' . esc_js($body) . '");';
	$output .= '</script>';


	if (empty($events->data)) {
		// Log when no events are found
		$output .= '<script>console.warn("No events found.");</script>';
		return $output . 'No events found.';
	}
	
	
	// Sort the events by the 'start -> formatted' field (event start date)
	usort($events->data, function($a, $b) {
		return strtotime($a->start->formatted) - strtotime($b->start->formatted);
	});

	// Build the output for displaying events
	$output .= '<div class="event-list">';
	foreach ($events->data as $event) {
		$event_image = $event->images->header;
		$event_title = $event->name;
		$event_description = $event->description;
		$event_date = $event->start->formatted;
		$event_time = date("g:i a", strtotime($event_date)); // "4:00 pm" 
		$event_url = $event->url;
		$event_cta = $event->call_to_action;

		$output .= '<div class="event is-style-column-box-shadow">';
		$output .= '<div class="top">';
		$output .= '<a href="' . esc_url($event_url) . '" target="_blank"><img src="' . esc_url($event_image) . '" alt="' . esc_attr($event_title) . '" /></a>';
		$output .= '<div class="event-text">';
		$output .= '<h2 class="has-base-font-size has-inter-font-family">' . esc_html($event_title) . '</h2>';
		$output .= '<p class="date has-x-small-font-size"><strong>' . date('F j', strtotime($event_date)) . ', ' . $event_time . '</strong></p>';
		$output .= '<div class="description">' . $event_description . '</div>';
		$output .= '</div>';

			$output .= '</div><div class="bottom">';
				$output .= '<a href="' . esc_url($event_url) . '" target="_blank" class="event-button has-x-small-font-size">'. esc_html( $event_cta ) . '</a>';
		$output .= '</div></div>';
	}
	$output .= '</div>';

	return $output;
}

// Enqueue plugin stylesheet
function enqueue_event_styles() {
	wp_enqueue_style('event-api-plugin-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_event_styles');


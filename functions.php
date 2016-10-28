<?php

/**
 * Remove recipes and brewing categories from the Homepage
 *
 * @param WP_Query $query The WordPress Query
 *
 * @uses get_term_by()
 */
function rdcoll_remove_recipes_from_homepage( $query ) {
	// If this is the main query on the homepage
	if ( $query->is_main_query() && is_home() ) {
		// Get term objects for recipes and brewing
		$recipes = get_term_by( 'slug', 'recipes', 'category' );
		$brewing = get_term_by( 'slug', 'brewing', 'category' );

		// Set category__not_in for each of the term IDs
		$query->set( 'category__not_in', array( 
			$recipes->term_id, 
			$brewing->term_id, 
		));
	}
}
add_action( 'pre_get_posts', 'rdcoll_remove_recipes_from_homepage' );

/**
 * Create the HTML markup for a Road Trip insert
 *
 * @param string $from The trip origin, comprehensible to Google Maps
 * @param string $to The trip destination, comprehensible to Google Maps
 *
 * @global $rdcoll_driving_maps
 *
 * @uses esc_attr()
 * @uses esc_html()
 * 
 * @return string Markup for a Road Trip insert
 */
function rdcoll_travel_context_roadtrip( $from, $to ) {
	if ( ! $from || ! $to ) {
		return false;
	}

	$output = sprintf( "Driving from %s TO %s", $from, $to );

	global $rdcoll_driving_maps;

	if ( ! $rdcoll_driving_maps ) {
		$rdcoll_driving_maps = array();
	}

	// Create a unique map ID based on the origin and destination
	$map_id = 'map' . md5( serialize( array( $from, $to ) ) );

	// Send the map data to Javascript
	$rdcoll_driving_maps[ $map_id ] = array( $from, $to );

	$output = '<h3 class="meta-travel-title">Road Trip</h3>';

	// Add the airport codes to output
	$output .= sprintf( 
		'<div class="meta-road-route">%s to %s</div>', 
		esc_html( $from ), 
		esc_html( $to )
	);

	// Create the map div
	$output .= sprintf( 
		'<div class="rdcoll-google-map" id="%s" style="%s"></div>', 
		esc_attr( $map_id ), 
		'width:100%;height:300px;' 
	);

	return $output;
}

/**
 * Create the HTML markup for a Flight insert
 *
 * @param array $codes Array of flight stops
 *
 * @global $rdcoll_flight_maps
 *
 * @uses STYLESHEETPATH
 * @uses rdcoll_get_flight_data()
 * @uses wp_kses_post()
 * @uses esc_attr()
 * @uses esc_html()
 * 
 * @return string Markup for a Flight insert
 */
function rdcoll_travel_context_flight( $codes ) {
	/** 
	 * This is a global array of maps on the currently requested page. We need this to send data 
	 * to the footer function to generate the Google Maps javascript.
	 */
	global $rdcoll_flight_maps;

	// Require the airport data and functions
	require_once( STYLESHEETPATH . '/inc/airport-data.php' );
	require_once( STYLESHEETPATH . '/inc/airport-functions.php' );

	// Run the codes through the function that turns them into airport objects
	$data = rdcoll_get_flight_data( $codes );

	// Initialize an array if not set
	if ( ! is_array( $rdcoll_flight_maps ) ) {
		$rdcoll_flight_maps = array();
	}

	// Create a unique map ID based on the ICAO codes
	$map_id = 'map' . md5( serialize( $codes ) );

	// Add the map ID to the array
	$rdcoll_flight_maps[ $map_id ] = array();

	// Loop through the airports and add the latitude and longitude to the maps array
	foreach ( $data['airports'] as $airport ) {
		$rdcoll_flight_maps[ $map_id ][] = array( 
			'lat'     => floatval( $airport->latitude ), 
			'lng'     => floatval( $airport->longitude ), 
			'title'   => wp_kses_post( $airport->name ), 
			'code'    => wp_kses_post( $airport->code ), 
			'city'    => wp_kses_post( $airport->city ),
			'country' => wp_kses_post( $airport->country ), 
		);
	}

	$output = sprintf( '<h3 class="meta-travel-title">Flight: %s to %s</h3>', 
		esc_html( $data['departure']->city ), 
		esc_html( $data['arrival']->city )
	);

	// Add the airport codes to output
	$output .= sprintf( 
		'<div class="meta-airport-data"><div class="alignleft">%s miles</div><div class="alignright">%s</div></div>', 
		number_format( absint( $data['distance'] ) ),
		wp_kses_post( $data['codes_display'] )
	);

	// Create the map div
	$output .= sprintf( 
		'<div class="rdcoll-google-map" id="%s" style="%s"></div>', 
		esc_attr( $map_id ), 
		'width:100%;height:300px;' 
	);

	// Return the output for use on the page
	return $output;
}

/** 
 * Adds the travel shortcode to display travel plans in a blog post
 *
 * @param array $atts Shortcode attributes
 *
 * @uses rdcoll_travel_context_flight()
 * @uses rdcoll_travel_context_roadtrip()
 * @uses sanitize_text_field()
 * @uses apply_filters()
 * 
 * @return string Shortcode markup
 */
function rdcoll_travel_shortcode( $atts ) {
	if ( 'flight' == $atts['type'] ) {
		// Get an array of ICAO codes from the shortcode path attribute
		$codes = explode( ',', strtoupper( $atts['path'] ) );

		// Get the flight map and details
		$render = rdcoll_travel_context_flight( $codes );
	}

	if ( 'roadtrip' == $atts['type'] ) {
		// Where from and to?
		$from = sanitize_text_field( $atts['from'] );
		$to   = sanitize_text_field( $atts['to'] );

		// Get the roadtrip map and details
		$render = rdcoll_travel_context_roadtrip( $from, $to );
	}

	return apply_filters( 'rdcoll_travel_render_content', $render );
}
add_shortcode( 'travel', 'rdcoll_travel_shortcode' );

/**
 * Generates the Javascript for the travel maps in the site footer
 *
 * @global $rdcoll_flight_maps
 *
 * @uses esc_js()
 */
function rdcoll_travel_footer() {
	global $rdcoll_flight_maps, $rdcoll_driving_maps;

	?>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?key=AIzaSyBd-8sowiNbwA6da_z_yibOrL_gN-1Rs6M"></script>
	<script>
	jQuery( document ).ready( function( $ ) {
		// Global array of Google Map styles that apply to all maps
		var styleArray = [
			{
				featureType: "all",
				stylers: [ { saturation: -80 } ]
			}, {
				featureType: "road.arterial",
				elementType: "geometry",
				stylers: [ { hue: "#00ffee" }, { saturation: 50 } ]
			}, {
				featureType: "poi.business",
				elementType: "labels",
				stylers: [ { visibility: "off" } ]
			}
		];

		<?php

		// Loop through driving maps and create JS for each
		if ( is_array( $rdcoll_driving_maps ) ) {
			foreach ( $rdcoll_driving_maps as $map_id => $points ) {
				?>

				var origin<?php echo esc_js( $map_id ); ?> = '<?php echo esc_js( $points[0] ); ?>';
				var destination<?php echo esc_js( $map_id ); ?> = '<?php echo esc_js( $points[1] ); ?>';

				var <?php echo esc_js( $map_id ); ?> = new google.maps.Map( document.getElementById( '<?php echo esc_js( $map_id ); ?>' ), {
					styles: styleArray
				});

				var dr<?php echo esc_js( $map_id ); ?> = new google.maps.DirectionsRenderer({
					map: <?php echo esc_js( $map_id ); ?>,
					suppressMarkers: true
				});

				var request = {
					destination: destination<?php echo esc_js( $map_id ); ?>,
					origin: origin<?php echo esc_js( $map_id ); ?>,
					travelMode: google.maps.TravelMode.DRIVING
				};

				var ds = new google.maps.DirectionsService();

				ds.route( request, function( response, status ) {
					if ( google.maps.DirectionsStatus.OK == status ) {
						dr<?php echo esc_js( $map_id ); ?>.setDirections( response );

						var milesText = response.routes[0].legs[0].distance.text;
						milesText = ' (' + milesText.replace( 'mi', 'miles' ) + ')';
						var routeText = jQuery('#<?php echo esc_js( $map_id ); ?>').next('.meta-road-route');
						routeText.append( milesText );
					}
				});

				<?php
			}
		}

		// Loop through all maps and create each custom path
		if ( is_array( $rdcoll_flight_maps ) ) {
			foreach ( $rdcoll_flight_maps as $map_id => $map_points ) { 
				?>

				// Create an array of path coordinates
				var pathCoordinates = [
				<?php foreach ( $map_points as $map_point ) { ?>
				{ 
					lat:<?php echo esc_js( $map_point['lat'] ); ?>, 
					lng:<?php echo esc_js( $map_point['lng'] ); ?>, 
					title:'<?php echo esc_js( $map_point['title'] ); ?>',
					code:'<?php echo esc_js( $map_point['code'] ); ?>',
					city:'<?php echo esc_js( $map_point['city'] ); ?>',
					country:'<?php echo esc_js( $map_point['country'] ); ?>'
				},
				<?php } ?>];

				// Holder array for Latitude/Longitude list and GM bounds
				var LatLngList = [];
				var LatLngBounds = new google.maps.LatLngBounds();

				// Create the list of GPS coordinates
				for ( i = 0; i < pathCoordinates.length; i++ ) {
					LatLngList.push( new google.maps.LatLng( pathCoordinates[i].lat, pathCoordinates[i].lng ) );
				}

				// Ad each of the coordinates to the GM bounds
				LatLngList.forEach( function( LatLng ) {
					LatLngBounds.extend( LatLng );
				});

				// Create the polylines for the trip stops
				var flightPath = new google.maps.Polyline({
					path: pathCoordinates,
					geodesic: true,
					strokeColor: '#007acc',
					strokeOpacity: .5,
					strokeWeight: 3
				});

				// Create the map itself
				var <?php echo esc_js( $map_id ); ?> = new google.maps.Map( document.getElementById( '<?php echo esc_js( $map_id ); ?>' ), {
					scrollwheel: false,
					styles: styleArray
				});

				// Set the polylines on the map
				flightPath.setMap( <?php echo esc_js( $map_id ); ?> );

				// Center and bound the map
				<?php echo esc_js( $map_id ); ?>.setCenter( LatLngBounds.getCenter() );
				<?php echo esc_js( $map_id ); ?>.fitBounds( LatLngBounds );

				// Add markers for each stop
				LatLngList.forEach( function( LatLng, idx ) {
					var contentString = '<div id="map-marker-content">' +
						'<div class="title">' + pathCoordinates[idx].title + ' (' + pathCoordinates[idx].code + ')</div>' +
						'<div class="location">' + pathCoordinates[idx].city + ', ' + pathCoordinates[idx].country + '</div>' +
						'<div class="link"><a href="http://www.airnav.com/airport/' + pathCoordinates[idx].code + '" target="_blank">AirNav Link</a></div>' +
						'</div>';

					var infowindow = new google.maps.InfoWindow({
						content: contentString
					});

					var marker = new google.maps.Marker({
						position: LatLng,
						icon: { path: google.maps.SymbolPath.CIRCLE, scale: 3 },
						draggable: false,
						map: <?php echo esc_js( $map_id ); ?>
					});

					marker.addListener( 'click', function() {
						infowindow.open( <?php echo esc_js( $map_id ); ?>, marker );
					});
				});
		
				<?php 
			} 
		}
		?>
	});
	</script>

	<?php
}
add_action( 'wp_footer', 'rdcoll_travel_footer' );

function rdcoll_wp_head() {
	?>
	<style>
	h3.meta-travel-title { margin-bottom:0; }
	.meta-road-route,
	.meta-airport-data div { margin-bottom:8px; }
	
	</style>
	<?php
}
add_action( 'wp_head', 'rdcoll_wp_head' );

// omit
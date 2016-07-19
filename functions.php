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
 * Adds the travel shortcode to display travel plans in a blog post
 *
 * @param array $atts Shortcode attributes
 *
 * @global $rdcoll_google_maps
 * 
 * @uses STYLESHEETPATH
 * @uses rdcoll_get_trip_data()
 * @uses esc_attr()
 * @uses absint()
 * 
 * @return string Shortcode markup
 */
function rdcoll_travel_shortcode( $atts ) {
	if ( 'flight' !== $atts['type'] ) {
		return false;
	}

	/** 
	 * This is a global array of maps on the currently requested page. We need this to send data 
	 * to the footer function to generate the Google Maps javascript.
	 */
	global $rdcoll_google_maps;

	// Require the airport data and functions
	require_once( STYLESHEETPATH . '/inc/airport-data.php' );
	require_once( STYLESHEETPATH . '/inc/airport-functions.php' );

	// Get an array of ICAO codes from the shortcode path attribute
	$codes = explode( ',', strtoupper( $atts['path'] ) );

	// Run the codes through the function that turns them into airport objects
	$data = rdcoll_get_trip_data( $codes );

	// Initialize an array if not set
	if ( ! is_array( $rdcoll_google_maps ) ) {
		$rdcoll_google_maps = array();
	}

	// Create a unique map ID based on the ICAO codes
	$map_id = 'map' . md5( serialize( $codes ) );

	// Add the map ID to the array
	$rdcoll_google_maps[ $map_id ] = array();

	// Loop through the airports and add the latitude and longitude to the maps array
	foreach ( $data['airports'] as $airport ) {
		$rdcoll_google_maps[ $map_id ][] = array( 
			'lat'     => floatval( $airport->latitude ), 
			'lng'     => floatval( $airport->longitude ), 
			'title'   => wp_kses_post( $airport->name ), 
			'code'    => wp_kses_post( $airport->code ), 
			'city'    => wp_kses_post( $airport->city ),
			'country' => wp_kses_post( $airport->country ), 
		);
	}

	// Create the map div
	$output = sprintf( 
		'<div class="rdcoll-google-map" id="%s" style="%s"></div>', 
		esc_attr( $map_id ), 
		'width:100%;height:300px;' 
	);

	// Add the airport codes to output
	$output .= sprintf( 
		'<div class="meta-airport-codes">%s</div>', 
		wp_kses_post( $data['codes_display'] )
	);

	// Add the total mileage to the output
	$output .= sprintf( 
		'<div class="meta-travel-mileage">%s miles</div>', 
		number_format( absint( $data['distance'] ) )
	);

	// Return the output for use on the page
	return $output;
}
add_shortcode( 'travel', 'rdcoll_travel_shortcode' );

/**
 * Generates the Javascript for the travel maps in the site footer
 *
 * @global $rdcoll_google_maps
 *
 * @uses esc_js()
 */
function rdcoll_travel_footer() {
	global $rdcoll_google_maps;

	?>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?key=AIzaSyBd-8sowiNbwA6da_z_yibOrL_gN-1Rs6M"></script>
	<script>
	jQuery( document ).ready( function() {
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

		// Loop through all maps and create each custom path
		foreach ( $rdcoll_google_maps as $map_id => $map_points ) { 
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
		<?php } ?>
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'rdcoll_travel_footer' );

// omit
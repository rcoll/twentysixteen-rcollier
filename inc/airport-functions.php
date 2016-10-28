<?php

/**
 * Turn an array of codes into an array of airport objects
 *
 * @param array $codes Array of ICAO codes
 *
 * @uses rdcoll_get_airport_by_code()
 * @uses rdcoll_get_distance_between_airports()
 * 
 * @return array Array of airport objects
 */
function rdcoll_get_flight_data( $codes ) {
	$data = array();
	$data['airports'] = array();
	$data['distance'] = 0;

	// Loop through codes and get airport objects for each
	foreach ( $codes as $code ) {
		$data['airports'][] = rdcoll_get_airport_by_code( $code );
	}

	// Loop through airport chain and get total distance
	for ( $i = 0; $i < count( $codes ) - 1; $i++ ) {
		$data['distance'] += rdcoll_get_distance_between_airports( $data['airports'][ $i ], $data['airports'][ $i + 1] );
	}

	// Set departure and arrival airports
	$data['departure'] = $data['airports'][0];
	$data['arrival'] = $data['airports'][ count( $data['airports'] ) - 1 ];

	// Set codes into resulting object
	$data['codes'] = $codes;

	// Create a string for the codes display
	$data['codes_display'] = '';

	// Loop through codes and create a string of linked ICAO codes
	foreach ( $codes as $code ) {
		$data['codes_display'] .= wp_kses_post( sprintf( '<a href="http://www.airnav.com/airport/%s" target="_blank">%s</a> &rarr; ', $code, $code ) );
	}

	// Remove the trailing arrow
	$data['codes_display'] = rtrim( $data['codes_display'], ' &rarr; ' );

	return $data;
}

/**
 * Use an ICAO code to get an airports data
 *
 * @param string $code The ICAO code (3 or 4 alpha chars)
 *
 * @uses rdcoll_get_airport_data()
 *
 * @return stdClass Airport object
 */
function rdcoll_get_airport_by_code( $code ) {
	// Get all airport data
	$data = rdcoll_get_airport_data();

	// Loop through all the airports
	foreach ( $data as $d ) {
		// If the code matches, create the results object
		if ( $d[4] == $code ) {
			$airport = new stdClass;

			$airport->id = $d[0];
			$airport->name = $d[1];
			$airport->city = $d[2];
			$airport->country = $d[3];
			$airport->code = $d[4];
			$airport->latitude = $d[6];
			$airport->longitude = $d[7];
			$airport->altitude = $d[8];

			return $airport;
		}
	}

	return false;
}

/**
 * Use some basic trigonometry to calculate the distance between two GPS points
 *
 * @param stdClass $start The latitude and longitude of the first point
 * @param stdClass $end The latitude and longitude of the second point
 *
 * @uses absint()
 * 
 * @return int Miles between points
 */
function rdcoll_get_distance_between_airports( $start, $end ) {
	$lat1 = $start->latitude;
	$lon1 = $start->longitude;
	$lat2 = $end->latitude;
	$lon2 = $end->longitude;

	$theta = $lon1 - $lon2;
	$dist = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) +  cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
	$dist = acos( $dist );
	$dist = rad2deg( $dist );
	$miles = $dist * 60 * 1.1515;

	return absint( $miles );
}

// omit
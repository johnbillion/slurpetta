#!/usr/bin/env php
<?php

$handle = fopen( $argv[1], 'r' ) or die;

$scan_info = array(
	'plugins' => array(),
	'themes'  => array(),
	'core'  => array(),
);
$max_name_length = 0;

$install_types = [
	'plugins' => explode( "\n", @file_get_contents( 'plugins/.active_installs' ) ?: [] ),
	'themes' => explode( "\n", @file_get_contents( 'themes/.active_installs' ) ?: [] ),
	'core' => [
		'latest,0',
		'nightly,0',
	]
];
$all_active_installs = array();

foreach ( $install_types as $type => $installs ) {
	foreach ( $installs as $install ) {
		$install = explode( ',', $install );
		$all_active_installs[ $type ][ $install[0] ] = (int) $install[1];
	}
}

function save_current_info( string $type, string $slug ) {
	global $scan_info, $max_name_length, $all_active_installs;

	if ( isset( $scan_info[ $type ][ $slug ] ) ) {
		$scan_info[ $type ][ $slug ]['matches']++;
	} else {
		$scan_info[ $type ][ $slug ] = array(
			'matches' => 1,
			'installs' => $all_active_installs[ $type ][ $slug ] ?? 0,
		);
		$max_name_length = max( strlen( $slug ), $max_name_length );
	}
}

while ( ( $line = fgets( $handle ) ) !== false ) {
	if ( preg_match( '#^([^/]+)/[^/]/([^/]+)/#', $line, $match ) ) {
		save_current_info( $match[1], $match[2] );
	} elseif ( preg_match( '#^core/([^/]+)/#', $line, $match ) ) {
		save_current_info( 'core', $match[1] );
	}
}

fclose( $handle );

foreach ( $scan_info as $type => $items ) {
	uasort( $items, function( $a, $b ) {
		return ( $b['installs'] - $a['installs'] );
	} );

	$num_results = count( $items );
	fwrite( STDERR, sprintf(
		"\nMatching %s: %d\n",
		$type,
		$num_results
	) );

	if ( empty( $items ) ) {
		continue;
	}

	echo 'Matches  ' . str_pad( 'Slug', $max_name_length - 1 ) . "Active installs\n";
	echo '=======  ' . str_pad( '====', $max_name_length - 1 ) . "===============\n";

	foreach ( $items as $slug => $item ) {
		$result = $item['installs'] ?: null;

		if ( $result ) {
			$active_installs = str_pad(
				number_format( $result ),
				9, ' ', STR_PAD_LEFT
			) . '+';
		} else {
			$active_installs = '         -';
		}
		echo str_pad( $item['matches'], 7, ' ', STR_PAD_LEFT )
			. '  '
			. str_pad( $slug, $max_name_length )
			. '  '
			. "$active_installs\n";
	}
}

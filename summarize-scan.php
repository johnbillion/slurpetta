#!/usr/bin/env php
<?php

$handle = fopen( $argv[1], 'r' ) or die;

$scan_info       = array();
$current_plugin  = '';
$current_count   = 0;
$max_name_length = 0;

$installs = file_get_contents( 'plugins/.active_installs' );
$installs = explode( "\n", $installs );
$all_active_installs = array();

foreach ( $installs as $install ) {
	$install = explode( ',', $install );
	$all_active_installs[ $install[0] ] = (int) $install[1];
}

function save_current_plugin_info() {
	global $scan_info, $current_plugin, $current_count, $max_name_length, $all_active_installs;
	if ( $current_count > 0 ) {
		array_push( $scan_info, array(
			'plugin_name' => $current_plugin,
			'matches'     => $current_count,
			'installs'    => $all_active_installs[ $current_plugin ] ?? 0,
		) );
		$current_count = 0;
		$max_name_length = max( strlen( $current_plugin ), $max_name_length );
	}
}

while ( ( $line = fgets( $handle ) ) !== false ) {
	if ( preg_match( '#^(plugins/[^/]/)?([^/]+)/#', $line, $match ) ) {
		$plugin = $match[2];
		if ( $plugin !== $current_plugin ) {
			save_current_plugin_info();
			$current_plugin = $plugin;
		}
		$current_count++;
	}
}

fclose( $handle );

save_current_plugin_info();

usort( $scan_info, function( $a, $b ) {
	return ( $b['installs'] - $a['installs'] );
} );

$num_results = count( $scan_info );
fwrite( STDERR, sprintf(
	"%d matching plugin%s\n",
	$num_results,
	( $num_results === 1 ? '' : 's' )
) );

echo 'Matches  ' . str_pad( 'Plugin', $max_name_length - 3 ) . "Active installs\n";
echo '=======  ' . str_pad( '======', $max_name_length - 3 ) . "===============\n";

foreach ( $scan_info as $plugin ) {
	$result = $plugin['installs'] ?: null;

	if ( $result ) {
		$active_installs = str_pad(
			number_format( $result ),
			9, ' ', STR_PAD_LEFT
		) . '+';
	} else {
		// The plugins API returns `null` for nonexistent/removed plugins
		$active_installs = '   REMOVED';
	}
	echo str_pad( $plugin['matches'], 7, ' ', STR_PAD_LEFT )
		. '  '
		. str_pad( $plugin['plugin_name'], $max_name_length )
		. '  '
		. "$active_installs\n";
}

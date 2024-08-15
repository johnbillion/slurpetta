<?php declare( strict_types = 1 );

require_once 'formatting.php';

function fetch_popular_slugs( string $type, int $minimum_active_installs ): array {
	// The maximum number of results per page is 250.
	$url = 'https://api.wordpress.org/%1$s/info/1.2/?action=query_%1$s&request[browse]=popular&request[fields][active_installs]=1&request[per_page]=250&request[page]=%2$d';
	$page = 1;
	$pages = 1;
	$assets = [];

	do {
		echo "Page {$page}...\n";

		$data = json_decode( file_get_contents( sprintf( $url, $type, $page ) ), true );
		if ( ! $data ) {
			throw new Exception(
				sprintf(
					'Failed to download %s list.',
					$type
				)
			);
		}

		$pages = $data['info']['pages'];

		foreach ( $data[ $type ] as $result ) {
			$installs = (int) $result['active_installs'];
			if ( $installs >= $minimum_active_installs ) {
				$assets[ $result['slug'] ] = $installs;
			} elseif ( $type === 'plugins' ) {
				// The results for plugins are approximately ordered by active installs, so we can stop after this page of results.
				// The results for themes are ordered much more fuzzily, so we need to iterate all themes.
				$page = $pages;
			}

			if ( $installs >= 1000000 ) {
				$initial = $result['slug'][0];
				$target = __DIR__ . '/plugins/' . $initial . '/' . $result['slug'];
				$link = __DIR__ . '/popular/' . $result['slug'];

				if ( ! file_exists( $link ) ) {
					// Create a symlink to the plugin in the popular directory if it has more than 1 million active installations.
					symlink(
						$target,
						$link,
					);
				}
			}

			if ( $installs >= 5000000 ) {
				$initial = $result['slug'][0];
				$target = __DIR__ . '/plugins/' . $initial . '/' . $result['slug'];
				$link = __DIR__ . '/top/' . $result['slug'];

				if ( ! file_exists( $link ) ) {
					// Create a symlink to the plugin in the top directory if it has more than 5 million active installations.
					symlink(
						$target,
						$link,
					);
				}
			}
		}

		$page++;
	} while ( $page <= $pages );

	arsort( $assets );

	$download_path = $type . '/.active_installs';
	$lines = [];

	foreach ( $assets as $slug => $installs ) {
		$lines[] = "{$slug},{$installs}";
	}

	file_put_contents(
		$download_path,
		implode( "\n", $lines )
	);

	return array_keys( $assets );
}

function read_last_revision( string $type ): int {
	if ( file_exists( $type . '/.last-revision' ) ) {
		return (int) file_get_contents( $type . '/.last-revision' );
	} else {
		return 0;
	}
}

function write_last_revision( string $type, int $revision ): void {
	file_put_contents(
		$type . '/.last-revision',
		"$revision\n"
	);
}

function download( string $type, array $slugs, bool $is_partial_sync ): array {
	// Number of simultaneous downloads
	global $parallel;

	// Data structures defined previously for partial sync
	global $items, $revisions;

	if ( $is_partial_sync ) {
		$current_revision = $revisions[ count( $revisions ) - 1 ]['number'];
	}

	$stats = array(
		'total'   => count( $slugs ),
		'updated' => 0,
		'failed'  => 0,
	);

	$download_path = $type . '/.to_download';
	file_put_contents(
		$download_path,
		implode( "\n", $slugs )
	);

	// Start `xargs` to process downloads in parallel.
	$descriptors = array(
		0 => array( 'file', $download_path, 'r' ), // `xargs` will read from this file
		1 => array( 'pipe', 'w' ),                 // `xargs` will write to stdout
		2 => STDERR,
	);
	$xargs = proc_open(
		"xargs -n 1 -P $parallel ./download $type",
		$descriptors,
		$pipes
	);

	// Track which items are in progress and when they were started
	$in_progress = array();

	// Process output from `./download` script instances (newline-delimited
	// JSON messages).
	while ( ( $line = fgets( $pipes[1] ) ) !== false ) {
		$line = trim( $line );
		$data = json_decode( $line, true );
		if ( ! $data || ! $data['type'] || ! $data['slug'] ) {
			throw new Exception(
				"Invalid progress update message: $line"
			);
		}

		$slug = $data['slug'];

		switch ( $data['type'] ) {
			case 'start':
				$in_progress[ $slug ] = array(
					'started'       => time(),
					'download_path' => $data['download_path'],
					'download_url'  => $data['download_url'],
				);
				// No further action; go back to while() above
				continue 2;
			case 'done':
				$status = ' OK ';
				$stats['updated']++;
				unset( $in_progress[ $slug ] );
				break;
			case 'fail':
				$status = 'FAIL';
				$stats['failed']++;
				file_put_contents(
					$type . '/.failed_downloads',
					"$slug\n",
					FILE_APPEND
				);
				unset( $in_progress[ $slug ] );
				break;
			case 'error':
				throw new Exception(
					'Error from download script: ' . $data['details']
				);
			default:
				throw new Exception(
					'Unrecognized update type: ' . $data['type']
				);
		}

		$percent = str_pad(
			number_format(
				100 * ( $stats['updated'] + $stats['failed'] ) / $stats['total'],
				1
			) . '%',
			6, ' ', STR_PAD_LEFT
		) . '%'; // sprintf placeholder

		$message1 = "[$status] $percent  %s";
		$message2 = null;
		$m_plugin2 = null;

		if ( $is_partial_sync ) {
			// Look through each revision associated with this item and
			// un-mark the item as having a pending update.
			foreach ( $items[ $slug ] as $index ) {
				unset( $revisions[ $index ]['to_update'][ $slug ] );
			}
			// Look for revisions that have no more items left to update.
			$last_revision = $current_revision;
			for ( $i = count( $revisions ) - 1; $i >= 0; $i-- ) {
				if ( empty( $revisions[ $i ]['to_update'] ) ) {
					$current_revision = $revisions[ $i ]['number'];
					array_pop( $revisions );
				} else {
					break;
				}
			}
			if ( $current_revision !== $last_revision ) {
				$message2 = "-> local copy now at r$current_revision";
				write_last_revision( $type, $current_revision );
			}
		}

		if ( $is_partial_sync && ! $message2 ) {
			// The svn revision of the local copy should advance throughout a
			// partial sync, but sometimes this takes a while when we're
			// waiting on a large download.  Try to show progress in this case.
			$rev_waiting = $revisions[ count( $revisions ) - 1 ];
			foreach ( $in_progress as $p_plugin => &$p_info ) {
				if (
					isset( $rev_waiting['to_update'][ $p_plugin ] ) &&
					time() > $p_info['started'] + 30
				) {
					if ( ! isset( $p_info['size'] ) ) {
						// Do a HEAD request for the zip
						exec(
							"wget '$p_info[download_url]' --spider 2>&1",
							$p_output
						);
						$match = preg_match(
							'#^Length: ([0-9]+) #m',
							implode( "\n", $p_output ),
							$p_size
						);
						$p_info['size'] = $match ? (int) $p_size[1] : 0;
						// "Note that if the array already contains some
						// elements, exec() will append to the end of the
						// array."  Yay PHP!
						unset( $p_output, $p_size );
					}
					$p_percent = '';
					if ( ! empty( $p_info['size'] ) ) {
						clearstatcache();
						$file_size = @filesize( $p_info['download_path'] );
						$p_percent = ' '
							. floor( $file_size * 100 / $p_info['size'] )
							. '%%';
					}
					$message2 = "[%s$p_percent]";
					$m_plugin2 = $p_plugin;
				}
			}
			unset( $p_info );
		}

		echo fit_message( $message1, $slug, $message2, $m_plugin2 ) . "\n";
	}

	fclose( $pipes[1] );

	sleep(1);
	$status = proc_get_status( $xargs );
	proc_close( $xargs );

	if ( $status['running'] ) {
		throw new Exception(
			'xargs should not still be running'
		);
	}
	if ( $status['exitcode'] ) {
		throw new Exception(
			'unexpected xargs exit code: ' . $status['exitcode']
		);
	}

	return $stats;
}

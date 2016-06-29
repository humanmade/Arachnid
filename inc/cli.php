<?php

namespace Arachnid;
use WP_CLI;
use WP_CLI_Command;
use DateTime;

WP_CLI::add_command( 'arachnid', __NAMESPACE__ . '\\Arachnid_CLI' );

class Arachnid_CLI extends WP_CLI_Command {

	function delete_date( $args, $assoc_args ) {
		if ( ! isset( $args[0] ) ){
			return WP_CLI::error( 'No date provided.' );
		}
		$datetime = DateTime::createFromFormat( 'U', strtotime( $args[0] ) );
		if ( empty( $datetime ) ) {
			return WP_CLI::error( 'Invalid date provided.' );
		}

		$start = mktime( 0, 0, 0, $datetime->format( 'n' ), $datetime->format( 'j' ), $datetime->format( 'Y' ) );
		$end = mktime( 23, 59, 59, $datetime->format( 'n' ), $datetime->format( 'j' ), $datetime->format( 'Y' ) );
		$query = [
			'route'  => '/omnivore/v1/webhook',
			'after'  => $start,
			'before' => $end,
			'limit'  => 10,
		];
		$results = \Arachnid\Entry::query( $query );
		$entries = $results->items;

		$total = $results->total;
		$current = 0;

		while ( $current < $total ) {
			foreach ( $entries as $entry ) {
				$current++;

				$id = $entry->get_id();
				do_action( 'arachnid.delete', $id );
				$delete = \Arachnid\Entry::delete( $id );
				if ( true !== $delete ) {
					WP_CLI::error( sprintf( 'Deleting %d failed! Error: %s', $id, $delete ) );
				} else {
					WP_CLI::success( sprintf( 'Arachnid ID %d Deleted', $id ) );
				}
			}

			if ( $current >= $total ) {
				break;
			}

			// Bump the offset, and go again.
			WP_CLI::line( sprintf( 'Fetching %d-%d', $current, $current + 9 ) );
			$query['offset'] = $current;
			$results = \Arachnid\Entry::query( $query );
			$entries = $results->items;
		}

	}


}

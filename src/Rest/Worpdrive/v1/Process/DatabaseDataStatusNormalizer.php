<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process;

class DatabaseDataStatusNormalizer {

	/**
	 * A failed request's `error_context` is passed on untouched and never used as the map: the failed request made
	 * no archive, so none of its progress reaches WorpDrive, and WorpDrive resends the map it sent.
	 */
	public function normalize( array $status ) :array {
		if ( !\is_string( $status[ 'href' ] ?? null ) ) {
			$status[ 'href' ] = '';
		}

		if ( !\is_array( $status[ 'table_export_map' ] ?? null ) ) {
			$status[ 'table_export_map' ] = [];
		}

		return $status;
	}
}

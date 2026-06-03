<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process;

use FernleafSystems\WorpdriveClient\Database\Data\ExportMap;

class DatabaseDataStatusNormalizer {

	public function normalize( array $status ) :array {
		if ( !\is_string( $status[ 'href' ] ?? null ) ) {
			$status[ 'href' ] = '';
		}

		if ( !\is_array( $status[ 'table_export_map' ] ?? null ) ) {
			$status[ 'table_export_map' ] = [];
		}

		$contextMap = $status[ 'error_context' ][ 'table_export_map' ] ?? null;
		$normalizedContextMap = $this->normalizeValidTableExportMap( $contextMap );
		if ( empty( $status[ 'table_export_map' ] ) && $normalizedContextMap !== null ) {
			$status[ 'table_export_map' ] = $normalizedContextMap;
		}

		return $status;
	}

	private function normalizeValidTableExportMap( $map ) :?array {
		if ( !\is_array( $map ) || empty( $map ) ) {
			return null;
		}

		try {
			return ( new ExportMap( $map ) )->status();
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}
}

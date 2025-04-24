<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

use FernleafSystems\Wordpress\Services\Services;

class TableEnum {

	/**
	 * @throws \Exception
	 */
	public function enum( array $exclusions = [] ) :array {
		$DB = Services::WpDb();
		$tableStatus = $DB->showTableStatus( ARRAY_A );
		if ( !\is_array( $tableStatus ) ) {
			throw new \Exception( 'showTableStatus() did not return an array.' );
		}
		$tables = [];
		foreach ( $tableStatus as $s ) {
			if ( empty( $s[ 'Name' ] ) ) {
				throw new \Exception( 'The name field for a table was empty. This is irregular.' );
			}
			if ( \str_starts_with( $s[ 'Name' ], $DB->getPrefix() ) ) {
				$excluded = false;
				if ( !empty( $exclusions ) ) {
					$exPrefix = \preg_replace( sprintf( '#^%s#', \preg_quote( $DB->getPrefix(), '#' ) ), '', $s[ 'Name' ] );
					foreach ( $exclusions as $exclusion ) {
						if ( \preg_match( $exclusion, $exPrefix ) ) {
							$excluded = true;
							break;
						}
					}
				}
				if ( !$excluded && !empty( $s[ 'Engine' ] ) ) {
					$tables[ $s[ 'Name' ] ] = [
						'name'               => $s[ 'Name' ],
						'rows'               => empty( $s[ 'Rows' ] ) ? (int)$DB->getVar( sprintf( "SELECT COUNT(*) AS `total_records` FROM `%s`", $s[ 'Name' ] ) ) : $s[ 'Rows' ],
						'average_row_length' => $s[ 'Avg_row_length' ] ?? 0,
						'bytes'              => $s[ 'Data_length' ] ?? 0,
						'size'               => \round( ( ( $s[ 'Data_length' ] ?? 0 ) + ( $s[ 'Index_length' ] ?? 0 ) )/1024/1024, 2 ),
						'engine'             => \strtolower( $s[ 'Engine' ] )
					];
				}
			}
		}
		return $tables;
	}
}
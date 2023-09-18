<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes;

use FernleafSystems\Wordpress\Services\Services;

class BuildDataForDays {

	public const ZERO_DATE_FORMAT = 'never';
	/** must be this date format for parsing query */
	public const STANDARD_DATE_FORMAT = 'Y-m-d';

	public function buildFromOldestToNewest( int $oldest, ?int $newest = null ) :array {
		$days = [];

		$carbon = Services::Request()->carbon( true );
		if ( $newest !== null ) {
			$carbon->setTimestamp( $newest );
		}

		$dateFormat = Services::WpGeneral()->getOption( 'date_format' );
		do {
			$stdFormat = $carbon->format( 'Y-m-d' );
			$days[ $stdFormat ] = [
				'label' => $carbon->format( $dateFormat ),
				'value' => $stdFormat,
			];
			$carbon->subDay();
		} while ( $carbon->timestamp > $oldest );

		return $days;
	}

	public function build( array $timestamps ) :array {
		$days = [];
		$dateFormat = Services::WpGeneral()->getOption( 'date_format' );
		$carbon = Services::Request()->carbon( true );
		foreach ( \array_map( '\intval', $timestamps ) as $ts ) {
			if ( empty( $ts ) ) {
				$label = __( 'Never', 'wp-simple-firewall' );
				$stdFormat = self::ZERO_DATE_FORMAT;
			}
			else {
				$carbon->setTimestamp( $ts );
				$label = $carbon->format( $dateFormat );
				/** must be this date format for parsing query */
				$stdFormat = $carbon->format( self::STANDARD_DATE_FORMAT );
			}

			if ( !isset( $days[ $stdFormat ] ) ) {
				$days[ $stdFormat ] = [
					'label' => $label,
					'value' => $stdFormat,
				];
			}
		}
		return \array_values( $days );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops\Record;
use FernleafSystems\Wordpress\Services\Services;

class MfaRecordsForDisplay {

	/**
	 * @param Record[] $records
	 * @return list<array{id:string,label:string,used_at:string,reg_at:string}>
	 */
	public function run( array $records ) :array {
		return $this->build( $records, true );
	}

	/**
	 * @param Record[] $records
	 * @return list<array{id:string,label:string,used_at:string,reg_at:string}>
	 */
	public function runWithoutDateLabels( array $records ) :array {
		return $this->build( $records, false );
	}

	/**
	 * @param Record[] $records
	 * @return list<array{id:string,label:string,used_at:string,reg_at:string}>
	 */
	private function build( array $records, bool $includeDateLabels ) :array {
		/**
		 * Order by most recently used first, then most recently registered.
		 */
		\usort( $records, function ( Record $a, Record $b ) {
			$atA = $a->used_at;
			$atB = $b->used_at;
			if ( $atA === $atB ) {
				$atA = $a->created_at;
				$atB = $b->created_at;
				$ret = $atA == $atB ? 0 : ( $atA > $atB ? -1 : 1 );
			}
			else {
				$ret = $atA > $atB ? -1 : 1;
			}
			return $ret;
		} );

		return \array_map(
			function ( Record $record ) use ( $includeDateLabels ) {
				return [
					'id'      => $record->unique_id,
					'label'   => $record->label,
					'used_at' => $this->formatRecordTimestamp(
						(int)$record->used_at,
						__( 'Used', 'wp-simple-firewall' ),
						__( 'Never', 'wp-simple-firewall' ),
						$includeDateLabels
					),
					'reg_at'  => $this->formatRecordTimestamp(
						(int)$record->created_at,
						__( 'Registered', 'wp-simple-firewall' ),
						__( 'Unknown', 'wp-simple-firewall' ),
						$includeDateLabels
					)
				];
			},
			$records
		);
	}

	private function formatRecordTimestamp( int $timestamp, string $label, string $emptyValue, bool $includeLabel ) :string {
		$value = $timestamp === 0 ? $emptyValue :
			Services::Request()
					->carbon( true )
					->setTimestamp( $timestamp )
					->diffForHumans();

		return $includeLabel ? sprintf( '%s: %s', $label, $value ) : $value;
	}
}

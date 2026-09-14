<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

/**
 * @phpstan-type State array{
 *   abspath:string,
 *   last_analysis_started_at:int,
 *   last_locks_created_at:int,
 *   last_locks_created_failed_at:int,
 *   last_error:string,
 *   cipher:string,
 *   cipher_last_checked_at:int
 * }
 * @phpstan-type StoredState array{
 *   abspath?:string,
 *   last_analysis_started_at?:int,
 *   last_locks_created_at?:int,
 *   last_locks_created_failed_at?:int,
 *   last_error?:string,
 *   cipher?:string,
 *   cipher_last_checked_at?:int
 * }
 */
class FileLockerState {

	private const INT_FIELDS = [
		'last_analysis_started_at',
		'last_locks_created_at',
		'last_locks_created_failed_at',
		'cipher_last_checked_at',
	];

	private const STRING_FIELDS = [
		'last_error',
		'cipher',
	];

	/**
	 * @return State
	 */
	public function build( array $stored ) :array {
		$state = \array_merge( $this->defaults(), $stored );

		return [
			'abspath'                      => ( new NormalizeAbsPath() )->normalize( $this->pathOrCurrent( $state[ 'abspath' ] ) ),
			'last_analysis_started_at'     => $this->intOrDefault( $state[ 'last_analysis_started_at' ] ),
			'last_locks_created_at'        => $this->intOrDefault( $state[ 'last_locks_created_at' ] ),
			'last_locks_created_failed_at' => $this->intOrDefault( $state[ 'last_locks_created_failed_at' ] ),
			'last_error'                   => $this->stringOrDefault( $state[ 'last_error' ] ),
			'cipher'                       => $this->stringOrDefault( $state[ 'cipher' ] ),
			'cipher_last_checked_at'       => $this->intOrDefault( $state[ 'cipher_last_checked_at' ] ),
		];
	}

	/**
	 * @return StoredState
	 */
	public function prepareForStorage( array $state ) :array {
		$stored = [];
		if ( \array_key_exists( 'abspath', $state ) ) {
			$stored[ 'abspath' ] = ( new NormalizeAbsPath() )->normalize( $this->pathOrCurrent( $state[ 'abspath' ] ) );
		}

		foreach ( self::INT_FIELDS as $key ) {
			if ( \array_key_exists( $key, $state ) ) {
				$stored[ $key ] = $this->intOrDefault( $state[ $key ] );
			}
		}

		foreach ( self::STRING_FIELDS as $key ) {
			if ( \array_key_exists( $key, $state ) ) {
				$stored[ $key ] = $this->stringOrDefault( $state[ $key ] );
			}
		}

		return $stored;
	}

	/**
	 * @return State
	 */
	private function defaults() :array {
		return [
			'abspath'                      => ABSPATH,
			'last_analysis_started_at'     => 0,
			'last_locks_created_at'        => 0,
			'last_locks_created_failed_at' => 0,
			'last_error'                   => '',
			'cipher'                       => '',
			'cipher_last_checked_at'       => 0,
		];
	}

	private function pathOrCurrent( $value ) :string {
		return \is_string( $value ) && $value !== '' ? $value : ABSPATH;
	}

	private function intOrDefault( $value ) :int {
		return \is_numeric( $value ) ? (int)$value : 0;
	}

	private function stringOrDefault( $value ) :string {
		return \is_string( $value ) ? $value : '';
	}
}

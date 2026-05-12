<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * @phpstan-type RawOptionRow array{option_id:int,option_name:string,option_value:string,autoload:string}
 * @phpstan-type RawOptionStoreState array{option_name:string,exists:bool,row:RawOptionRow|null}
 */
class RawOptionStoreSnapshot {

	/**
	 * @return array<string,RawOptionStoreState>
	 */
	public function snapshot() :array {
		$snapshot = [];

		foreach ( $this->optionStoreNames() as $storeKey => $optionName ) {
			$row = $this->fetchRawOptionRow( $optionName );
			$snapshot[ $storeKey ] = [
				'option_name' => $optionName,
				'exists'      => $row !== null,
				'row'         => $row,
			];
		}

		return $snapshot;
	}

	/**
	 * @param array<string,mixed> $snapshot
	 * @return array<string,RawOptionStoreState>
	 */
	public function normalize( array $snapshot, string $label ) :array {
		$normalized = [];
		foreach ( $this->optionStoreNames() as $storeKey => $optionName ) {
			if ( !\is_array( $snapshot[ $storeKey ] ?? null ) ) {
				throw new \RuntimeException( $label.' state is missing raw option store metadata.' );
			}

			$store = $snapshot[ $storeKey ];
			foreach ( [ 'option_name', 'exists', 'row' ] as $requiredKey ) {
				if ( !\array_key_exists( $requiredKey, $store ) ) {
					throw new \RuntimeException( $label.' state is missing raw option store metadata.' );
				}
			}
			$normalized[ $storeKey ] = [
				'option_name' => (string)$store[ 'option_name' ],
				'exists'      => (bool)$store[ 'exists' ],
				'row'         => $this->normalizeRawOptionRow(
					\is_array( $store[ 'row' ] ) ? $store[ 'row' ] : null
				),
			];

			if ( $normalized[ $storeKey ][ 'option_name' ] !== $optionName ) {
				throw new \RuntimeException( $label.' state has mismatched raw option store metadata.' );
			}
			if ( $normalized[ $storeKey ][ 'exists' ] && $normalized[ $storeKey ][ 'row' ] === null ) {
				throw new \RuntimeException( $label.' state is missing raw option row metadata.' );
			}
			if ( !$normalized[ $storeKey ][ 'exists' ] && $normalized[ $storeKey ][ 'row' ] !== null ) {
				throw new \RuntimeException( $label.' state has mismatched raw option row metadata.' );
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $snapshot
	 */
	public function restore( array $snapshot, string $label ) :void {
		$normalized = $this->normalize( $snapshot, $label );
		foreach ( \array_keys( $this->optionStoreNames() ) as $storeKey ) {
			$store = $normalized[ $storeKey ];
			if ( (bool)$store[ 'exists' ] ) {
				$row = $store[ 'row' ];
				if ( !\is_array( $row ) ) {
					throw new \RuntimeException( $label.' state is missing raw option row metadata.' );
				}
				$this->restoreRawOptionRow( $row );
			}
			else {
				$this->deleteRawOptionRow( $store[ 'option_name' ] );
			}
		}
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	/**
	 * @return array{opts_all:string,opts_free:string,opts_pro:string}
	 */
	private function optionStoreNames() :array {
		$con = RuntimeTestState::controller();
		return [
			'opts_all'  => $con->prefix( 'opts_all', '_' ),
			'opts_free' => $con->prefix( 'opts_free', '_' ),
			'opts_pro'  => $con->prefix( 'opts_pro', '_' ),
		];
	}

	/**
	 * @param array<string,mixed>|null $row
	 * @return RawOptionRow|null
	 */
	private function normalizeRawOptionRow( ?array $row ) :?array {
		if ( $row === null ) {
			return null;
		}
		foreach ( [ 'option_id', 'option_name', 'option_value', 'autoload' ] as $requiredKey ) {
			if ( !\array_key_exists( $requiredKey, $row ) ) {
				return null;
			}
		}

		$normalized = [
			'option_id'    => (int)$row[ 'option_id' ],
			'option_name'  => (string)$row[ 'option_name' ],
			'option_value' => (string)$row[ 'option_value' ],
			'autoload'     => (string)$row[ 'autoload' ],
		];
		return $normalized[ 'option_id' ] > 0 && $normalized[ 'option_name' ] !== ''
			? $normalized
			: null;
	}

	/**
	 * @return RawOptionRow|null
	 */
	private function fetchRawOptionRow( string $optionName ) :?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$optionName
			),
			\ARRAY_A
		);

		return \is_array( $row ) ? $this->normalizeRawOptionRow( $row ) : null;
	}

	/**
	 * @phpstan-param RawOptionRow $row
	 */
	private function restoreRawOptionRow( array $row ) :void {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s OR option_id = %d",
				$row[ 'option_name' ],
				$row[ 'option_id' ]
			)
		);
		if ( $deleted === false ) {
			throw new \RuntimeException( 'Failed to prepare raw option row restore: '.$row[ 'option_name' ] );
		}

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_id, option_name, option_value, autoload) VALUES (%d, %s, %s, %s)",
				$row[ 'option_id' ],
				$row[ 'option_name' ],
				$row[ 'option_value' ],
				$row[ 'autoload' ]
			)
		);
		if ( $inserted === false ) {
			throw new \RuntimeException( 'Failed to restore raw option row: '.$row[ 'option_name' ] );
		}

		$this->clearRawOptionCaches( $row[ 'option_name' ] );
	}

	private function deleteRawOptionRow( string $optionName ) :void {
		global $wpdb;

		$result = $wpdb->delete( $wpdb->options, [ 'option_name' => $optionName ], [ '%s' ] );
		if ( $result === false ) {
			throw new \RuntimeException( 'Failed to delete raw option row: '.$optionName );
		}
		$this->clearRawOptionCaches( $optionName );
	}

	private function clearRawOptionCaches( string $optionName ) :void {
		\wp_cache_delete( $optionName, 'options' );
		\wp_cache_delete( 'alloptions', 'options' );
		\wp_cache_delete( 'notoptions', 'options' );
	}
}

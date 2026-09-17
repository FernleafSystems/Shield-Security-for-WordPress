<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ObservationStore {

	use PluginControllerConsumer;

	private const OPTION_CLIENT_IMPORT = 'importexport_client_observation';
	private const OPTION_UNASSOCIATED_REJECTION = 'importexport_unassociated_rejection';

	public function readClientImport() :?array {
		return $this->read( self::OPTION_CLIENT_IMPORT );
	}

	public function saveClientImport( array $observation ) :bool {
		return $this->save( self::OPTION_CLIENT_IMPORT, $observation );
	}

	public function deleteClientImport() :void {
		$this->delete( self::OPTION_CLIENT_IMPORT );
	}

	public function readUnassociatedRejection() :?array {
		return $this->read( self::OPTION_UNASSOCIATED_REJECTION );
	}

	public function saveUnassociatedRejection( array $observation ) :bool {
		return $this->save( self::OPTION_UNASSOCIATED_REJECTION, $observation );
	}

	private function read( string $suffix ) :?array {
		try {
			return SyncObservation::normalize( \get_option( $this->optionKey( $suffix ), false ) );
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	private function save( string $suffix, array $observation ) :bool {
		$observation = SyncObservation::normalize( $observation );
		if ( $observation === null ) {
			return false;
		}

		try {
			$key = $this->optionKey( $suffix );
			$current = \get_option( $key, false );
			if ( $current === false ) {
				return \add_option( $key, $observation, '', false );
			}
			return $current === $observation || \update_option( $key, $observation, false );
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	private function delete( string $suffix ) :void {
		try {
			\delete_option( $this->optionKey( $suffix ) );
		}
		catch ( \Throwable $e ) {
		}
	}

	private function optionKey( string $suffix ) :string {
		return self::con()->prefix( $suffix, '_' );
	}
}

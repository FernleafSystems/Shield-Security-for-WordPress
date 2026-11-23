<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Services\Services;

class PluginImportExport_HandshakeConfirm extends PluginImportExport_Base {

	public const SLUG = 'importexport_handshake';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		if ( Services::Request()->ts() < self::con()->opts->optGet( 'importexport_handshake_expires_at' ) ) {
			echo \wp_json_encode( [ 'success' => true ] );
			die();
		}
		$this->response()->setPayload()->setPayloadSuccess( false );
	}
}

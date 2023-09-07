<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class PluginImportExport_HandshakeConfirm extends PluginImportExport_Base {

	public const SLUG = 'importexport_handshake';

	protected function exec() {
		if ( Services::Request()->ts() <
			 (int)self::con()->getModule_Plugin()->opts()->getOpt( 'importexport_handshake_expires_at' ) ) {
			echo \json_encode( [ 'success' => true ] );
			die();
		}
		$this->response()->action_response_data = [
			'success' => false,
		];
	}
}
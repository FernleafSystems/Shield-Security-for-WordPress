<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class PluginImportExport_HandshakeConfirm extends PluginImportExport_Base {

	public const SLUG = 'importexport_handshake';

	protected function exec() {
		if ( Services::Request()->ts() < self::con()->opts->optGet( 'importexport_handshake_expires_at' ) ) {
			echo \wp_json_encode( [ 'success' => true ] );
			die();
		}
		$this->response()->action_response_data = [
			'success' => false,
		];
	}
}
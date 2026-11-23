<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Services\Services;

class PluginDeleteForceOff extends BaseAction {

	public const SLUG = 'delete_forceoff';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$file = Services::WpFs()->findFileInDir( 'forceoff', self::con()->getRootDir(), false );
		if ( !empty( $file ) ) {
			Services::WpFs()->deleteFile( $file );
			\clearstatcache();
		}

		$this->response()->setPayload( [
			'page_reload' => true,
			'message'     => __( 'Removed the forceoff file.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( true );
	}
}

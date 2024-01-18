<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class PluginDeleteForceOff extends BaseAction {

	public const SLUG = 'delete_forceoff';

	protected function exec() {
		$file = Services::WpFs()->findFileInDir( 'forceoff', self::con()->getRootDir(), false );
		if ( !empty( $file ) ) {
			Services::WpFs()->deleteFile( $file );
			\clearstatcache();
		}

		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => true,
			'message'     => __( 'Removed the forceoff file.', 'wp-simple-firewall' ),
		];
	}
}
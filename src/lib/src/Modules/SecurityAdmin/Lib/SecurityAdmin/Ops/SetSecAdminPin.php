<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SetSecAdminPin {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run( string $pin ) {
		if ( empty( $pin ) ) {
			throw new \Exception( 'Attempting to set an empty Security Admin Access Key.' );
		}
		if ( !self::con()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security Admin Access Key.' );
		}

		self::con()
			->opts
			->optSet( 'admin_access_key', wp_hash_password( $pin ) )
			->store();
	}
}
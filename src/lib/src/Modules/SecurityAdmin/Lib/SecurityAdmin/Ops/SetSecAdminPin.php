<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SetSecAdminPin {

	use ModConsumer;

	/**
	 * @param string $pin
	 * @throws \Exception
	 */
	public function run( string $pin ) {
		if ( empty( $pin ) ) {
			throw new \Exception( 'Attempting to set an empty Security Admin Access Key.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security Admin Access Key.' );
		}

		$this->getOptions()
			 ->setOpt( 'admin_access_key', md5( $pin ) );
		$this->getMod()
			 ->setIsMainFeatureEnabled( true )
			 ->saveModOptions();
	}
}
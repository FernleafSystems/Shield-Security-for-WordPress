<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SetSecAdminPin {

	use ModConsumer;

	/**
	 * @param string $sKey
	 * @throws \Exception
	 */
	public function run( $sKey ) {
		if ( empty( $sKey ) ) {
			throw new \Exception( 'Attempting to set an empty Security Admin Access Key.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security Admin Access Key.' );
		}

		$this->getOptions()
			 ->setOpt( 'admin_access_key', md5( $sKey ) );
		$this->getMod()
			 ->setIsMainFeatureEnabled( true )
			 ->saveModOptions();
	}
}
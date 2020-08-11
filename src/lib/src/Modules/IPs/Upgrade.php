<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_905() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oDBH = $oMod->getDbHandler_IPs();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$oDBH->getTable(), 'ip', $oDBH->enumerateColumns()[ 'ip' ] )
		);
	}

	/**
	 * Support larger transgression counts [smallint(1) => int(10)]
	 */
	protected function upgrade_911() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oDBH = $oMod->getDbHandler_IPs();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$oDBH->getTable(), 'transgressions', $oDBH->enumerateColumns()[ 'transgressions' ] )
		);
	}

	/**
	 * Support Magic Links for logged-in users.
	 */
	protected function upgrade_920() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$current = $oOpts->getOpt( 'user_auto_recover' );
		if ( !is_array( $current ) ) {
			$current = ( $current === 'gasp' ) ? [ 'gasp' ] : [];
			$oOpts->setOpt( 'user_auto_recover', $current );
		}
	}
}
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_905() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		$DBH = $mod->getDbHandler_IPs();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$DBH->getTable(), 'ip', $DBH->getColumnsDefinition()[ 'ip' ] )
		);
	}

	/**
	 * Support larger transgression counts [smallint(1) => int(10)]
	 */
	protected function upgrade_911() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$DBH = $oMod->getDbHandler_IPs();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$DBH->getTable(), 'transgressions', $DBH->getColumnsDefinition()[ 'transgressions' ] )
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
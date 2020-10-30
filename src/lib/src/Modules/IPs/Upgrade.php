<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1010() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var Delete $del */
		$del = $mod->getDbHandler_IPs()->getQueryDeleter();
		$del->filterByLabel( 'iControlWP' )->query();
	}

	protected function upgrade_905() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_IPs()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table, 'ip', $schema->enumerateColumns()[ 'ip' ] )
		);
	}

	/**
	 * Support larger transgression counts [smallint(1) => int(10)]
	 */
	protected function upgrade_911() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		$schema = $mod->getDbHandler_IPs()->getTableSchema();
		Services::WpDb()->doSql(
			sprintf( "ALTER TABLE `%s` MODIFY `%s` %s;",
				$schema->table,
				'transgressions',
				$schema->enumerateColumns()[ 'transgressions' ]
			)
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
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class Upgrade extends ExecOnceModConsumer {

	protected function run() {
		$this->upgradeModule();
		$this->runEveryUpgrade();
		$this->upgradeCommon();
	}

	protected function runEveryUpgrade() {
	}

	protected function upgradeCommon() {
		$this->getMod()->saveModOptions( true );
	}

	/**
	 * Runs through each version with upgrade code available and if the current config
	 * version is less than the upgrade version, run the upgrade code.
	 */
	protected function upgradeModule() {
		$con = $this->getCon();
		$previous = $con->cfg->previous_version;
		foreach ( $con->cfg->version_upgrades as $version ) {
			$upgradeMethod = 'upgrade_'.str_replace( '.', '', $version );
			if ( version_compare( $previous, $version, '<' ) && method_exists( $this, $upgradeMethod ) ) {
				$this->{$upgradeMethod}();
			}
		}
	}
}
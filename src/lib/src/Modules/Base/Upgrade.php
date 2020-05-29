<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Upgrade {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	protected function run() {
		$version = $this->getOptions()->getOpt( 'cfg_version' );
		if ( empty( $version ) ) {
			$version = '9.0.2'; // TODO: delete after next release is propagated
		}
		$this->upgradeModule( $version );
		$this->runEveryUpgrade();
		$this->upgradeCommon();
	}

	/**
	 * @return string[]
	 */
	protected function getUpgrades() {
		return [
			'9.0.0',
			'9.0.3',
		];
	}

	protected function runEveryUpgrade() {
	}

	protected function upgradeCommon() {
		$this->getOptions()->setOpt( 'cfg_version', $this->getCon()->getVersion() );
		$this->getMod()->saveModOptions( true );
	}

	/**
	 * @param string $sCurrent
	 */
	protected function upgradeModule( $sCurrent ) {
		foreach ( $this->getUpgrades() as $sVersion ) {
			$sMethod = 'upgrade_'.str_replace( '.', '', $sVersion );
			if ( version_compare( $sCurrent, $sVersion, '<' )
				 && method_exists( $this, $sMethod ) ) {
				$this->{$sMethod}();
			}
		}
	}
}
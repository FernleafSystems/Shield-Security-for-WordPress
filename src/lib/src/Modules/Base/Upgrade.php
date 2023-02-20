<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class Upgrade extends ExecOnceModConsumer {

	protected $previous;

	protected function canRun() :bool {
		return !empty( $this->previous ) && version_compare( $this->previous, $this->getCon()->getVersion(), '<' );
	}

	protected function run() {
		$this->upgradeModule();
		$this->upgradeCommon();
	}

	/**
	 * @return static
	 */
	public function setPrevious( string $previous ) {
		$this->previous = $previous;
		return $this;
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
		$upgrades = $con->cfg->version_upgrades;
		asort( $upgrades );
		foreach ( $upgrades as $version ) {
			$upgradeMethod = 'upgrade_'.str_replace( '.', '', $version );
			if ( version_compare( $this->previous, $version, '<' ) && method_exists( $this, $upgradeMethod ) ) {
				$this->{$upgradeMethod}();
			}
		}
	}
}
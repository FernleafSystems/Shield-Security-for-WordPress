<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

abstract class UpgradeHandlersBase {

	public const DEFAULT_UPGRADE_CHECK_INTERVAL = 168;

	protected $cfg;

	protected $upgrader;

	public function __construct( array $cfg ) {
		$this->cfg = $cfg;
		$this->upgrader = $this->run();
	}

	abstract public function run();

	public function forceUpdateCheck() {
		if ( !empty( $this->upgrader ) && \method_exists( $this->upgrader, 'checkForUpdates' ) ) {
			$this->upgrader->checkForUpdates();
		}
	}
}
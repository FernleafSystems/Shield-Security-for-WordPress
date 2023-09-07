<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class Processor extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Crons\PluginCronsConsumer;

	/**
	 * @param ModCon $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );
		add_action( 'init', [ $this, 'onWpInit' ], $this->getWpHookPriority( 'init' ) );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], $this->getWpHookPriority( 'wp_loaded' ) );
		$this->setupCronHooks();
	}

	public function onWpInit() {
	}

	public function onWpLoaded() {
		if ( self::con()->getModule_Plugin()->opts()->isOpt( 'enable_upgrade_admin_notice', 'Y' ) ) {
			add_filter( self::con()->prefix( 'admin_bar_menu_groups' ), [ $this, 'addAdminBarMenuGroup' ] );
		}
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		return $groups;
	}

	protected function getWpHookPriority( string $hook ) :int {
		switch ( $hook ) {
			case 'init':
				$pri = Shield\Controller\Plugin\HookTimings::INIT_PROCESSOR_DEFAULT;
				break;
			default:
				$pri = 10;
		}
		return $pri;
	}
}
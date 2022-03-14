<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;

abstract class Processor {

	use Shield\Crons\PluginCronsConsumer;
	use Shield\Modules\ModConsumer;
	use ExecOnce;

	/**
	 * @param ModCon $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );
		add_action( 'init', [ $this, 'onWpInit' ], $this->getWpHookPriority( 'init' ) );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], $this->getWpHookPriority( 'wp_loaded' ) );
		add_filter( $mod->prefix( 'admin_bar_menu_groups' ), [ $this, 'addAdminBarMenuGroup' ] );
		$this->setupCronHooks();
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		return $groups;
	}

	public function onWpInit() {
	}

	public function onWpLoaded() {
	}

	public function onModuleShutdown() {
	}

	protected function getWpHookPriority( string $hook ) :int {
		switch ( $hook ) {
			case 'init':
				$pri = 9;
				break;
			default:
				$pri = 10;
		}
		return $pri;
	}
}
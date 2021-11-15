<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\FirewallHandler;

class Processor extends BaseShield\Processor {

	private $firewallHandler;

	public function onWpInit() {
		$this->getFirewallHandler()->execute();
	}

	private function getFirewallHandler() :FirewallHandler {
		if ( !isset( $this->firewallHandler ) ) {
			$this->firewallHandler = ( new FirewallHandler() )->setMod( $this->getMod() );
		}
		return $this->firewallHandler;
	}

	protected function getWpHookPriority( string $hook ) :int {
		switch ( $hook ) {
			case 'init':
				$pri = 0;
				break;
			default:
				$pri = parent::getWpHookPriority( $hook );
		}
		return $pri;
	}
}
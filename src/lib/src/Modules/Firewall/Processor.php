<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\FirewallHandler;

class Processor extends BaseShield\Processor {

	/**
	 * @deprecated 12.0
	 */
	private $dieMessage;

	/**
	 * @deprecated 12.0
	 */
	protected $aPatterns;

	/**
	 * @deprecated 12.0
	 */
	private $aAuditBlockMessage;

	/**
	 * @deprecated 12.0
	 */
	private $params;

	private $firewallHandler;

	protected function run() {
	}

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

	private function getIfDoFirewallBlock() :bool {
		return false;
	}

	private function doPreFirewallBlock() {
	}

	private function doFirewallBlock() {
	}

	protected function getFirewallDieMessageForDisplay() :string {
		return '';
	}

	private function sendBlockEmail( string $recipient ) :bool {
		return false;
	}

	/**
	 * @deprecated 12.0
	 */
	private function getIfPerformFirewallScan() :bool {
		return false;
	}

	/**
	 * @deprecated 12.0
	 */
	private function isVisitorRequestPermitted() :bool {
		return true;
	}

	/**
	 * @deprecated 12.0
	 */
	protected function doPassCheckBlockExeFileUploads() :bool {
		return true;
	}

	/**
	 * @deprecated 12.0
	 */
	private function doPassCheck( string $blockKey ) :bool {
		return true;
	}

	protected function getFirewallPatterns( $key = null ) {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	protected function getFirewallDieMessage() :array {
		return [ $this->getMod()->getTextOpt( 'text_firewalldie' ) ];
	}

	/**
	 * @param string $msg
	 * @return $this
	 * @deprecated 12.0
	 */
	protected function addToFirewallDieMessage( string $msg ) {
		return $this;
	}

	/**
	 * @deprecated 12.0
	 */
	private function getParamsToCheck() :array {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	private function getRawRequestParams() :array {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	private function getFirewallBlockKeyName( string $blockKey ) :string {
		return '';
	}
}
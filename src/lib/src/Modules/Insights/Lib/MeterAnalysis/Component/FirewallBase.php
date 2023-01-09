<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Strings;

abstract class FirewallBase extends Base {

	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Firewall();
		return $mod->isModOptEnabled()
			   && $mod->getOptions()->isOpt( 'block_'.$this->getFirewallKey(), 'Y' );
	}

	public function href() :string {
		return $this->getCon()->getModule_Firewall()->isModOptEnabled() ?
			$this->link( 'block_'.$this->getFirewallKey() ) : $this->link( 'enable_firewall' );
	}

	public function title() :string {
		/** @var Strings $strings */
		$strings = $this->getCon()->getModule_Firewall()->getStrings();
		return $strings->getFirewallCategoryName( $this->getFirewallKey() );
	}

	public function descProtected() :string {
		return __( 'Firewall is configured to block this category of requests.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Firewall isn't configured to block this category of requests.", 'wp-simple-firewall' );
	}

	protected function getFirewallKey() :string {
		return explode( '_', static::SLUG, 2 )[ 1 ];
	}
}
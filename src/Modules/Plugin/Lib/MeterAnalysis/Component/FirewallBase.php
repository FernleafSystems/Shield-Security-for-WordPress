<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Firewall\FirewallCategoryNames;

abstract class FirewallBase extends Base {

	use Traits\OptConfigBased;

	public const WEIGHT = 4;

	protected function testIfProtected() :bool {
		return self::con()->opts->optIs( 'block_'.$this->getFirewallKey(), 'Y' );
	}

	protected function getOptConfigKey() :string {
		return 'block_'.$this->getFirewallKey();
	}

	public function title() :string {
		return sprintf( '%s - %s', __( 'Firewall', 'wp-simple-firewall' ), $this->getFirewallCategoryName() );
	}

	protected function categories() :array {
		return [ __( 'Firewall', 'wp-simple-firewall' ) ];
	}

	public function descProtected() :string {
		return sprintf( '%s: %s', $this->getFirewallCategoryName(),
			__( 'Firewall is configured to block this category of requests.', 'wp-simple-firewall' ) );
	}

	public function descUnprotected() :string {
		return sprintf( '%s: %s', $this->getFirewallCategoryName(),
			__( "Firewall isn't configured to block this category of requests.", 'wp-simple-firewall' ) );
	}

	protected function getFirewallKey() :string {
		return \explode( '_', static::slug(), 2 )[ 1 ];
	}

	protected function getFirewallCategoryName() :string {
		return ( new FirewallCategoryNames() )->getFor( $this->getFirewallKey() );
	}
}
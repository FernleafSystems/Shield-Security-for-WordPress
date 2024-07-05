<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class LockdownXmlrpc extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'lockdown_xmlrpc';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		return self::con()->opts->optIs( 'disable_xmlrpc', 'Y' );
	}

	protected function getOptConfigKey() :string {
		return 'disable_xmlrpc';
	}

	public function title() :string {
		return __( 'XML-RPC Lockdown', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Access to XML-RPC is disabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Access to XML-RPC is available.", 'wp-simple-firewall' );
	}
}
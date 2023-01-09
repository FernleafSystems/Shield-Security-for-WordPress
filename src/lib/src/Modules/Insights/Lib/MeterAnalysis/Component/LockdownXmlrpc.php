<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownXmlrpc extends Base {

	public const SLUG = 'lockdown_xmlrpc';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isXmlrpcDisabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Lockdown();
		return $mod->isModOptEnabled() ? $this->link( 'disable_xmlrpc' ) : $this->link( 'enable_lockdown' );
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
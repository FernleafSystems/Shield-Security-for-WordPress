<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class XmlRpcDisable extends Base {

	public function title() :string {
		return __( 'Block XML-RPC', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Disable the XML-RPC endpoint and block all requests.', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( '.', 'wp-simple-firewall' ),
		];
	}

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->optIsAndModForOptEnabled( 'disable_xmlrpc', 'Y' )? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}
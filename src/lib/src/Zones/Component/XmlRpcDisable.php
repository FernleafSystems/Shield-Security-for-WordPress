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

	protected function tooltip() :string {
		return __( 'Switch on/off access to the XML-RPC endpoint', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->opts->optIs( 'disable_xmlrpc', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "Requests to the XML-RPC endpoint are allowed on your site.", 'wp-simple-firewall' );
		}

		return $status;
	}
}
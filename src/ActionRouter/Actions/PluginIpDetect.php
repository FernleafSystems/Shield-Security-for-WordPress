<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class PluginIpDetect extends BaseAction {

	use SecurityAdminNotRequired;

	public const SLUG = 'ipdetect';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$ip = $this->fields()->string( 'ip', '' );
		self::con()->opts->optSet( 'ipdetect_at', Services::Request()->ts() );
		$source = ( new FindSourceFromIp() )->run( $ip );
		if ( !empty( $source ) ) {
			self::con()->opts->optSet( 'visitor_address_source', $source );
		}
		$this->response()->setPayload( [
			'message'   => empty( $source )
				? sprintf( __( 'Could not find source from IP: %s', 'wp-simple-firewall' ), \esc_html( $ip ) )
				: sprintf( __( 'IP Source Found: %s', 'wp-simple-firewall' ), $source ),
			'ip_source' => $source,
		] )->setPayloadSuccess( (bool)( !empty( $source ) ) );
	}
}

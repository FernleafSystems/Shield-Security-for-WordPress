<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class PluginIpDetect extends BaseAction {

	public const SLUG = 'ipdetect';

	protected function exec() {
		/** @var Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		$source = ( new FindSourceFromIp() )->run( Services::Request()->post( 'ip' ) );
		if ( !empty( $source ) ) {
			$opts->setVisitorAddressSource( $source );
		}
		$this->response()->action_response_data = [
			'success'   => !empty( $source ),
			'message'   => empty( $source ) ? 'Could not find source' : 'IP Source Found: '.$source,
			'ip_source' => $source,
		];
	}
}
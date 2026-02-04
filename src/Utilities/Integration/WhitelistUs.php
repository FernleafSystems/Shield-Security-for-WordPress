<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Find;

class WhitelistUs {

	use PluginControllerConsumer;

	public function all() :void {
		$this->wordfence();
	}

	public function wordfence() :void {
		if ( ( new Find() )->isPluginActive( Find::WORDFENCE ) && \method_exists( '\wordfence', 'whitelistIP' ) ) {
			foreach ( $this->getIpsForShield() as $ip ) {
				\wordfence::whitelistIP( $ip );
			}
		}
	}

	private function getIpsForShield() :array {
		$ips = Services::ServiceProviders()->getProviderInfo( ServiceProviders::PROVIDER_SHIELD )[ 'ips' ] ?? [];
		return \array_merge( $ips[ 4 ] ?? [], $ips[ 6 ] ?? [] );
	}
}
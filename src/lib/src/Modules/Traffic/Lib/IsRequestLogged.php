<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IsRequestLogged {

	use ModConsumer;

	private static $excluded = null;

	public function isLogged() :bool {
		return apply_filters( 'shield/is_log_traffic', !$this->isExcluded() );
	}

	private function isExcluded() :bool {
		if ( self::$excluded === null ) {
			self::$excluded = self::con()->plugin_deleting
							  || !$this->opts()->isTrafficLoggerEnabled()
							  || $this->isCustomExcluded()
							  || $this->isRequestTypeExcluded();
		}
		return self::$excluded;
	}

	private function isRequestTypeExcluded() :bool {
		$srvProviders = Services::ServiceProviders();
		$ipID = Services::IP()->getIpDetector()->getIPIdentity();
		$excl = $this->opts()->getOpt( 'type_exclusions' );
		$isLoggedIn = Services::WpUsers()->isUserLoggedIn();

		$exclude = ( \in_array( 'logged_in', $excl ) && $isLoggedIn )
				   || ( \in_array( 'cron', $excl ) && Services::WpGeneral()->isCron() )
				   || ( \in_array( 'server', $excl ) && $ipID === IpID::THIS_SERVER );

		if ( !$exclude && !$isLoggedIn ) {
			$exclude = ( \in_array( 'search', $excl ) && \in_array( $ipID, $srvProviders->getSearchProviders() ) )
					   || ( \in_array( 'uptime', $excl ) && \in_array( $ipID, $srvProviders->getUptimeProviders() ) );
		}

		return $exclude;
	}

	private function isCustomExcluded() :bool {
		$req = Services::Request();

		$agent = $req->getUserAgent();
		$path = $req->getPath().( empty( $_GET ) ? '' : '?'.\http_build_query( $_GET ) );

		$exclude = false;
		foreach ( $this->opts()->getCustomExclusions() as $excl ) {
			if ( \stripos( $agent, $excl ) !== false || \stripos( $path, $excl ) !== false ) {
				$exclude = true;
				break;
			}
		}
		return $exclude;
	}
}
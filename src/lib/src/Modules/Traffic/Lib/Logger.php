<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 12.0
 */
class Logger {

	use ModConsumer;

	public function run() {
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isTrafficLoggerEnabled() ) {
			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
				if ( $this->isRequestToBeLogged() ) {
					$this->logTraffic();
				}
			} );
		}
	}

	private function isRequestToBeLogged() :bool {
		return !$this->getCon()->plugin_deleting
			   && apply_filters( 'shield/is_log_traffic',
				!$this->isCustomExcluded() && !$this->isRequestTypeExcluded()
			   );
	}

	private function isRequestTypeExcluded() :bool {
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		$excl = $opts->getReqTypeExclusions();
		$bLoggedIn = Services::WpUsers()->isUserLoggedIn();

		$exclude = ( in_array( 'simple', $excl ) && count( Services::Request()->getRawRequestParams( false ) ) == 0 )
				   || ( in_array( 'logged_in', $excl ) && $bLoggedIn )
				   || ( in_array( 'ajax', $excl ) && Services::WpGeneral()->isAjax() )
				   || ( in_array( 'cron', $excl ) && Services::WpGeneral()->isCron() );

		if ( !$exclude && !$bLoggedIn ) {
			$exclude = ( in_array( 'search', $excl ) && $this->isServiceIp_Search() )
					   || ( in_array( 'uptime', $excl ) && $this->isServiceIp_Uptime() );
		}

		return $exclude;
	}

	private function isCustomExcluded() :bool {
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$agent = $req->getUserAgent();
		$path = $req->getPath().( empty( $_GET ) ? '' : '?'.http_build_query( $_GET ) );

		$exclude = false;
		foreach ( $opts->getCustomExclusions() as $excl ) {
			if ( stripos( $agent, $excl ) !== false || stripos( $path, $excl ) !== false ) {
				$exclude = true;
			}
		}
		return $exclude;
	}

	private function isServiceIp_Search() :bool {
		return in_array( Services::IP()->getIpDetector()->getIPIdentity(),
			Services::ServiceProviders()->getSearchProviders() );
	}

	private function isServiceIp_Uptime() :bool {
		return in_array( Services::IP()->getIpDetector()->getIPIdentity(),
			Services::ServiceProviders()->getUptimeProviders() );
	}

	private function logTraffic() {
	}
}
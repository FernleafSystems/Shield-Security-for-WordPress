<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use Monolog\Logger;

class RequestLogger extends ExecOnceModConsumer {

	/**
	 * @var Logger
	 */
	private $logger;

	protected function canRun() :bool {
		return Logger::API >= 2;
	}

	protected function run() {
		$this->getLogger()
			 ->pushHandler( ( new LogHandlers\LocalDbWriter() )->setMod( $this->getMod() ) );

		$this->pushCustomHandlers();

		add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
			if ( $this->isRequestToBeLogged() ) {
				$this->getLogger()->log( 'debug', 'log request' );
			}
		}, 1000 ); // high enough to come after audit trail
	}

	private function pushCustomHandlers() {
		$custom = apply_filters( 'shield/custom_request_log_handlers', [] );
		array_map(
			function ( $handler ) {
				$this->getLogger()->pushHandler( $handler );
			},
			( $this->getCon()->isPremiumActive() && is_array( $custom ) ) ? $custom : []
		);
	}

	private function isRequestToBeLogged() :bool {
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		return !$this->getCon()->plugin_deleting
			   && apply_filters( 'shield/is_log_traffic', $opts->isTrafficLoggerEnabled() && !$this->isCustomExcluded() && !$this->isRequestTypeExcluded() );
	}

	public function getLogger() :Logger {
		if ( !isset( $this->logger ) ) {
			$this->logger = new Logger( 'request', [], array_map( function ( $processorClass ) {
				return new $processorClass();
			}, $this->enumMetaProcessors() ) );
		}
		return $this->logger;
	}

	protected function enumMetaProcessors() :array {
		return [
			Processors\ShieldMetaProcessor::class,
			Processors\RequestMetaProcessor::class,
			Processors\UserMetaProcessor::class,
			Processors\WpMetaProcessor::class,
		];
	}

	private function isRequestTypeExcluded() :bool {
		$srvProviders = Services::ServiceProviders();
		$ipID = Services::IP()->getIpDetector()->getIPIdentity();
		/** @var Traffic\Options $opts */
		$opts = $this->getOptions();
		$excl = $opts->getReqTypeExclusions();
		$isLoggedIn = Services::WpUsers()->isUserLoggedIn();

		$exclude = ( in_array( 'logged_in', $excl ) && $isLoggedIn )
				   || ( in_array( 'cron', $excl ) && Services::WpGeneral()->isCron() )
				   || ( in_array( 'server', $excl ) && $ipID === IpID::THIS_SERVER );

		if ( !$exclude && !$isLoggedIn ) {
			$exclude = ( in_array( 'search', $excl ) && in_array( $ipID, $srvProviders->getSearchProviders() ) )
					   || ( in_array( 'uptime', $excl ) && in_array( $ipID, $srvProviders->getUptimeProviders() ) );
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
				break;
			}
		}
		return $exclude;
	}
}
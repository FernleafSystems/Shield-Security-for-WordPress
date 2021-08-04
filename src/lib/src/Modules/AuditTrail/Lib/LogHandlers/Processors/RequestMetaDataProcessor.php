<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Monolog\Processor\ProcessorInterface;

class RequestMetaDataProcessor implements ProcessorInterface {

	use PluginControllerConsumer;

	/**
	 * @param array $record
	 * @return array
	 */
	public function __invoke( array $record ) {
		$WP = Services::WpGeneral();
		$WPU = Services::WpUsers();

		$uid = $WPU->getCurrentWpUserId();
		if ( empty( $uid ) ) {
			if ( $WP->isCron() ) {
				$uid = 'cron';
			}
			elseif ( $WP->isWpCli() ) {
				$uid = 'wpcli';
			}
			else {
				$uid = false;
			}
		}

		$req = Services::Request();
		$record[ 'extra' ][ 'request_meta' ] = array_filter( [
			'ip'         => ( $uid == 'wpcli' ) ? '' : (string)Services::IP()->getRequestIp(),
			'rid'        => $this->getCon()->getShortRequestId(),
			'uid'        => $uid,
			'req_ua'     => ( $uid == 'wpcli' ) ? '' : $req->getUserAgent(),
			'req_method' => ( $uid == 'wpcli' ) ? '' : $req->getMethod(),
			'req_path'   => ( $uid == 'wpcli' ) ? '' : $req->getPath(),
		] );

		return $record;
	}
}

<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Processors;

use FernleafSystems\Wordpress\Services\Services;
use Monolog\Processor\ProcessorInterface;

class UserMetaProcessor implements ProcessorInterface {

	/**
	 * @param array $record
	 * @return array
	 */
	public function __invoke( array $record ) {
		$WP = Services::WpGeneral();

		$uid = Services::WpUsers()->getCurrentWpUserId();
		if ( empty( $uid ) ) {
			if ( $WP->isWpCli() ) {
				$uid = 'wpcli';
			}
			elseif ( $WP->isCron() ) {
				$uid = 'cron';
			}
			else {
				$uid = false;
			}
		}

		$record[ 'extra' ][ 'meta_user' ] = array_filter( [
			'uid' => $uid,
		] );

		return $record;
	}
}

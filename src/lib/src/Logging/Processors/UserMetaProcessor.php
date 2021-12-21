<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Services\Services;
use Monolog\Processor\ProcessorInterface;

class UserMetaProcessor implements ProcessorInterface {

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$WP = Services::WpGeneral();

		$uid = Services::WpUsers()->getCurrentWpUserId();
		if ( empty( $uid ) ) {
			if ( $WP->isWpCli() ) {
				$uid = 'cli';
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
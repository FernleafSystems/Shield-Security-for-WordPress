<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\CaptureNotBot;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs extends ExecOnceModConsumer {

	protected function canRun() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1
			   || $this->isForcedForOptimisationPlugins()
			   || ( $req->ts() - ( new BotSignalsRecord() )
					->setMod( $this->getMod() )
					->setIP( $this->getCon()->this_req->ip )
					->retrieveNotBotAt() ) > MINUTE_IN_SECONDS*45;
	}

	/**
	 * Looks for the presence of certain caching plugins and forces notbot to load.
	 */
	private function isForcedForOptimisationPlugins() :bool {
		return (bool)apply_filters(
			'shield/notbot_force_load',
			$this->getOptions()->isOpt( 'force_notbot', 'Y' )
			||
			!empty( array_intersect(
				array_map( 'basename', Services::WpPlugins()->getActivePlugins() ),
				[
					'breeze.php',
					'wpFastestCache.php',
					'wp-cache.php', // Super Cache
					'wp-hummingbird.php',
					'sg-cachepress.php',
					'autoptimize.php',
					'wp-optimize.php',
				]
			) ) > 0
		);
	}

	protected function run() {
		$this->enqueueJS();
		$this->nonceJs();
	}

	protected function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/notbot';
			return $enqueues;
		} );
	}

	/**
	 * @since 11.2 - don't fire for GTMetrix page requests
	 */
	private function nonceJs() {
		add_filter( 'shield/custom_localisations', function ( array $localz ) {
			$localz[] = [
				'shield/notbot',
				'shield_vars_notbotjs',
				apply_filters( 'shield/notbot_data_js', [
					'ajax'  => [
						'not_bot' => ActionData::Build( CaptureNotBot::SLUG ),
					],
					'flags' => [
						'run' => !in_array( Services::IP()->getIpDetector()->getIPIdentity(), [ 'gtmetrix' ] ),
					],
				] )
			];
			return $localz;
		} );
	}
}
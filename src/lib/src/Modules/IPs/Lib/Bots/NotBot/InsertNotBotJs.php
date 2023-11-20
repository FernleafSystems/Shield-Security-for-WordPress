<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionDataVO;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CaptureNotBot;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\CaptureNotBotNonce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1
			   || $this->isForcedForOptimisationPlugins()
			   || ( $req->ts() - ( new BotSignalsRecord() )
					->setIP( self::con()->this_req->ip )
					->retrieveNotBotAt() ) > \MINUTE_IN_SECONDS*45;
	}

	/**
	 * Looks for the presence of certain caching plugins and forces notbot to load.
	 */
	private function isForcedForOptimisationPlugins() :bool {
		return (bool)apply_filters(
			'shield/notbot_force_load',
			$this->opts()->isOpt( 'force_notbot', 'Y' )
			||
			!empty( \array_intersect(
				\array_map( 'basename', Services::WpPlugins()->getActivePlugins() ),
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
	}

	protected function enqueueJS() {
		add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {
			$assets[] = 'notbot';

			add_filter( 'shield/custom_localisations/components', function ( array $components ) {
				$components[ 'notbot' ] = [
					'key'      => 'notbot',
					'required' => !\in_array( Services::IP()->getIpDetector()->getIPIdentity(), [ 'gtmetrix' ] ),
					'handles'  => [
						'notbot',
					],
					'data'     => function () {
						$notBotVO = new ActionDataVO();
						$notBotVO->action = CaptureNotBot::class;
						$notBotVO->ip_in_nonce = false;

						$notBotNonceVO = new ActionDataVO();
						$notBotNonceVO->action = CaptureNotBotNonce::class;
						$notBotNonceVO->excluded_fields = [
							ActionData::FIELD_NONCE,
							ActionData::FIELD_AJAXURL,
						];

						return [
							'ajax'  => [
								'not_bot'       => ActionData::BuildVO( $notBotVO ),
								'not_bot_nonce' => ActionData::BuildVO( $notBotNonceVO ),
							],
							'flags' => [
								'run' => !\in_array( Services::IP()->getIpDetector()->getIPIdentity(), [ 'gtmetrix' ] ),
							],
						];
					},
				];
				return $components;
			} );

			/**
			 * @since      11.2 - don't fire for GTMetrix page requests
			 * @deprecated 18.5
			 */
			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				return $localz;
			} );

			return $assets;
		} );
	}
}
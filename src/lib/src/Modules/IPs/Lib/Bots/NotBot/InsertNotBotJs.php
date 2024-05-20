<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionDataVO,
	Actions\CaptureNotBot
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class InsertNotBotJs {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/notbot_js_insert', true );
	}

	protected function run() {
		add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {
			$assets[] = 'notbot';

			add_filter( 'shield/custom_localisations/components', function ( array $components ) {
				$components[ 'notbot' ] = [
					'key'     => 'notbot',
					'handles' => [
						'notbot',
					],
					'data'    => function () {
						$notBotVO = new ActionDataVO();
						$notBotVO->action = CaptureNotBot::class;
						$notBotVO->ip_in_nonce = false;

						return [
							'ajax'  => [
								'not_bot' => ActionData::BuildVO( $notBotVO ),
							],
							'flags' => [
								'skip'     => $this->isSkip(),
								'required' => $this->isFreshSignalRequired(),
							],
							'vars'  => [
								'altcha' => ( new AltChaHandler() )->generateChallenge(),
							]
						];
					},
				];
				return $components;
			} );

			return $assets;
		} );
	}

	/**
	 * Skip NotBot if the current visitor is a known, identifiable entity.
	 */
	private function isSkip() :bool {
		return !\in_array( Services::IP()->getIpDetector()->getIPIdentity(), [ IpID::VISITOR, IpID::UNKNOWN ], true );
	}

	private function isFreshSignalRequired() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1 ||
			   ( !$this->isSkip() && !empty( self::con()->comps->not_bot->getNonRequiredSignals() ) );
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
}
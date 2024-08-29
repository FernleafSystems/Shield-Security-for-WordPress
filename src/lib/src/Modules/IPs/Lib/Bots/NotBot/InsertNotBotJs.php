<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionDataVO,
	Actions\CaptureNotBot
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs {

	use ExecOnce;
	use PluginControllerConsumer;

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
								'skip'     => false,
								'required' => $this->isFreshSignalRequired(),
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
	 * @deprecated 20.0.8
	 */
	private function isSkip() :bool {
		return false;
	}

	private function isFreshSignalRequired() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1 ||
			   ( !$this->isSkip() && !empty( self::con()->comps->not_bot->getRequiredSignals() ) );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionDataVO,
	Actions\CaptureNotBot,
	Actions\CaptureNotBotNonce
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

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
								'required' => $this->isFreshSignalRequired(),
							],
						];
					},
				];
				return $components;
			} );

			return $assets;
		} );
	}

	private function isFreshSignalRequired() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1 ||
			   (
				   ( $req->ts() - self::con()->comps->not_bot->getLastNotBotSignalAt() > \MINUTE_IN_SECONDS*30 )
				   && Services::IP()->getIpDetector()->getIPIdentity() !== 'gtmetrix'
			   );
	}
}
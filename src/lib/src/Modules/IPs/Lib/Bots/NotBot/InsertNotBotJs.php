<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs extends ExecOnceModConsumer {

	protected function canRun() :bool {
		$req = Services::Request();
		return $req->query( 'force_notbot' ) == 1
			   || ( $req->ts() - ( new BotSignalsRecord() )
					->setMod( $this->getMod() )
					->setIP( Services::IP()->getRequestIp() )
					->retrieve()->notbot_at ) > MINUTE_IN_SECONDS*45;
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

			$ajaxData = $this->getMod()->getAjaxActionData( 'not_bot' );
			$ajaxHref = $ajaxData[ 'ajaxurl' ];
			unset( $ajaxData[ 'ajaxurl' ] );

			$localz[] = [
				'shield/notbot',
				'shield_vars_notbotjs',
				apply_filters( 'shield/notbot_data_js', [
					'ajax'  => [
						'not_bot' => http_build_query( $ajaxData )
					],
					'hrefs' => [
						'ajax' => $ajaxHref
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
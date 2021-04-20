<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return ( Services::Request()->ts() - ( new BotSignalsRecord() )
					->setMod( $this->getMod() )
					->setIP( Services::IP()->getRequestIp() )
					->retrieve()->notbot_at ) > MINUTE_IN_SECONDS*30;
	}

	protected function run() {
		$this->enqueueJS();
		$this->nonceJs();
	}

	protected function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/antibot';
			return $enqueues;
		} );
	}

	private function nonceJs() {
		add_filter( 'shield/custom_localisations', function ( array $localz ) {

			$ajaxData = $this->getMod()->getAjaxActionData( 'not_bot' );
			$ajaxHref = $ajaxData[ 'ajaxurl' ];
			unset( $ajaxData[ 'ajaxurl' ] );

			$localz[] = [
				'shield/antibot',
				'shield_vars_antibotjs',
				[
					'ajax'  => [
						'not_bot' => http_build_query( $ajaxData )
					],
					'hrefs' => [
						'ajax' => $ajaxHref
					],
				]
			];
			return $localz;
		} );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class InsertNotBotJs {

	use ModConsumer;

	public function run() {
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
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Resources\Dynamic;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs {

	use ModConsumer;

	// TODO: Workaround in-case cache space isn't writable
	public function run() {
		$this->enqueueJS();
		$this->buildJS();
	}

	private function buildJS() {
		$resource = 'shield/antibot.js';
		$dyn = ( new Dynamic() )->setCon( $this->getCon() );
		if ( !$dyn->resourceExists( $resource )
			 || Services::Request()->ts() - $dyn->getModifiedTime( $resource ) > 59 ) {
			$dyn->resourceDelete( $resource );
			$dyn->resourceCreate( $resource, $this->renderJs() );
		}
	}

	protected function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/antibot';
			return $enqueues;
		} );
	}

	private function renderJs() :string {
		$ajaxData = $this->getMod()->getAjaxActionData( 'not_bot' );
		$ajaxHref = $ajaxData[ 'ajaxurl' ];
		unset( $ajaxData[ 'ajaxurl' ] );

		$lines = preg_split( '/\r\n|\r|\n/', $this->getMod()->renderTemplate(
			'/snippets/anti_bot/not_bot.twig',
			[
				'ajax'  => [
					'not_bot' => http_build_query( $ajaxData )
				],
				'hrefs' => [
					'ajax' => $ajaxHref
				],
			]
		) );
		array_shift( $lines );
		array_pop( $lines );
		return implode( "\n", $lines );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Services\Services;

class SupportCandy extends Base {

	protected function run() {
		add_filter( 'wpsc_before_create_ticket_args', function ( $args ) {
			if ( $this->isBotBlockRequired() ) {
				Services::WpGeneral()->wpDie( $this->getCommonSpamMessage() );
			}
			return $args;
		}, 1000 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( '\WPSC_VERSION' ) && \version_compare( \WPSC_VERSION, '2.2.3', '>=' );
	}
}
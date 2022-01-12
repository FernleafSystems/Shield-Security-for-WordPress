<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Services\Services;

class SupportCandy extends Base {

	protected function run() {
		add_filter( 'wpsc_before_create_ticket_args', function ( $args ) {
			if ( $this->isSpam() ) {
				Services::WpGeneral()->wpDie( sprintf( "Sorry, your request failed %s Bot checking.",
					$this->getCon()->getHumanName() ) );
			}
			return $args;
		}, 1000 );
	}

	public static function IsProviderInstalled() :bool {
		return @class_exists( 'Support_Candy' )
			   && defined( 'WPSC_VERSION' ) && version_compare( WPSC_VERSION, '2.2.3', '>=' );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class BotActions extends Base {

	public function title() :string {
		return __( 'Bot Actions', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( "Decide how %s should respond when a bot performs certain actions.", 'wp-simple-firewall' ),
			self::con()->labels->Name );
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::BAD;
	}
}
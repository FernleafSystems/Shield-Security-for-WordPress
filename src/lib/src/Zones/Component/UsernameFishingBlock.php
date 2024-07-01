<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class UsernameFishingBlock extends Base {

	public function title() :string {
		return __( 'Block Username Fishing', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block requests that attempt to fish for WordPress usernames.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->opts->optIs( 'block_author_discovery', 'Y' ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}
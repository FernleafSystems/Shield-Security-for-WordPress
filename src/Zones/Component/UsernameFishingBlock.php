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

	protected function tooltip() :string {
		return __( 'Switch on/off username fishing detection', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->opts->optIs( 'block_author_discovery', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "It's possible to detect the usernames of authors+ on your site.", 'wp-simple-firewall' );
		}

		return $status;
	}
}
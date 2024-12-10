<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class FileEditingBlock extends Base {

	public function title() :string {
		return __( 'Restrict WP File Editing', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Restrict the ability to edit files from within the WordPress admin area.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Switch on/off file editing within WP', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->opts->optIs( 'disable_file_editing', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "It's possible to edit files from within the WordPress admin area.", 'wp-simple-firewall' );
		}

		return $status;
	}
}
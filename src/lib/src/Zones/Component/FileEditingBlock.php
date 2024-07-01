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

	public function enabledStatus() :string {
		return self::con()->opts->optIs( 'disable_file_editing', 'Y' ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}
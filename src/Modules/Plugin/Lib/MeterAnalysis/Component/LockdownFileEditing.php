<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class LockdownFileEditing extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'lockdown_file_editing';

	protected function testIfProtected() :bool {
		return self::con()->opts->optIs( 'disable_file_editing', 'Y' );
	}

	protected function getOptConfigKey() :string {
		return 'disable_file_editing';
	}

	public function title() :string {
		return __( 'WordPress File Editing Lockdown', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Editing files from within the WordPress admin area is disabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Editing files from within the WordPress admin area is allowed.", 'wp-simple-firewall' );
	}
}
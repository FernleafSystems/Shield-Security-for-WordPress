<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownFileEditing extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'lockdown_file_editing';

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->opts();
		return $mod->isModOptEnabled() && $opts->isOptFileEditingDisabled();
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
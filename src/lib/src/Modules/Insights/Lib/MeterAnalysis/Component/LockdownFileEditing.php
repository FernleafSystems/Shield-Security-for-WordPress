<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownFileEditing extends Base {

	public const SLUG = 'lockdown_file_editing';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isOptFileEditingDisabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Lockdown();
		return $mod->isModOptEnabled() ? $this->link( 'disable_file_editing' ) : $this->link( 'enable_lockdown' );
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
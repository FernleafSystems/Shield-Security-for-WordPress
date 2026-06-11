<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\ThemeReinstaller;

class ThemeReinstall extends ScansBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'theme_reinstall';

	protected function exec() {
		$stylesheet = sanitize_text_field( wp_unslash( $this->action_data[ 'stylesheet' ] ?? '' ) );
		$success = ( new ThemeReinstaller() )->reinstall( $stylesheet );

		$this->response()->setPayload( [
			'message'     => $success ? __( 'Theme re-installed. Reloading...', 'wp-simple-firewall' ) : __( 'Re-install failed.', 'wp-simple-firewall' ),
			'page_reload' => $success
		] )->setPayloadSuccess( $success );
	}
}

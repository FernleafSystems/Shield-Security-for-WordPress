<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities\PluginReinstaller;

class PluginReinstall extends ScansBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_reinstall';

	protected function exec() {
		$file = sanitize_text_field( wp_unslash( $this->action_data[ 'file' ] ?? '' ) );
		$success = ( new PluginReinstaller() )->reinstall( $file );

		$this->response()->setPayload( [
			'message'     => $success ? __( 'Plugin re-installed. Reloading...', 'wp-simple-firewall' ) : __( 'Re-install failed.', 'wp-simple-firewall' ),
			'page_reload' => $success
		] )->setPayloadSuccess( $success );
	}
}

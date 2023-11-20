<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Delete;
use FernleafSystems\Wordpress\Services\Services;

class PluginReinstall extends ScansBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_reinstall';

	protected function exec() {
		$success = false;

		$file = sanitize_text_field( wp_unslash( $this->action_data[ 'file' ] ?? '' ) );

		if ( $this->action_data[ 'reinstall' ] ?? false ) {
			$WPP = Services::WpPlugins();
			$plugin = $WPP->getPluginAsVo( $file );
			if ( $plugin->isWpOrg() && $WPP->reinstall( $plugin->file ) ) {
				try {
					( new Delete() )
						->setAsset( $plugin )
						->run();
					$success = true;
				}
				catch ( \Exception $e ) {
				}
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $success ? __( 'Plugin re-installed. Reloading...' ) : __( 'Re-install failed.' ),
			'page_reload' => $success
		];
	}
}
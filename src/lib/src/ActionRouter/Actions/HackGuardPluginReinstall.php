<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Delete;
use FernleafSystems\Wordpress\Services\Services;

class HackGuardPluginReinstall extends ScansBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_reinstall';

	protected function exec() {
		$success = false;

		$req = Services::Request();

		$activate = $req->post( 'activate' );
		$file = sanitize_text_field( wp_unslash( $req->post( 'file' ) ) );

		if ( $req->post( 'reinstall' ) ) {
			$WPP = Services::WpPlugins();
			$plugin = $WPP->getPluginAsVo( $file );
			if ( $plugin->isWpOrg() && $WPP->reinstall( $plugin->file ) ) {
				try {
					( new Delete() )
						->setMod( $this->getMod() )
						->setAsset( $plugin )
						->run();
					$success = true;
				}
				catch ( \Exception $e ) {
				}
			}
		}

		if ( $activate ) {
			Services::WpPlugins()->activate( $file );
		}

		$this->response()->success = $success;
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Services\Services;

class HackGuardPluginReinstall extends ScansBase {

	use SecurityAdminNotRequired;

	public const SLUG = 'plugin_reinstall';

	protected function exec() {
		$req = Services::Request();

		$activate = $req->post( 'activate' );
		$file = sanitize_text_field( wp_unslash( $req->post( 'file' ) ) );

		if ( $req->post( 'reinstall' ) ) {
			$activate = $this->getCon()
							 ->getModule_HackGuard()
							 ->getScansCon()
							 ->AFS()
							 ->actionPluginReinstall( $file );
		}

		if ( $activate ) {
			Services::WpPlugins()->activate( $file );
		}

		$this->response()->success = true;
	}
}
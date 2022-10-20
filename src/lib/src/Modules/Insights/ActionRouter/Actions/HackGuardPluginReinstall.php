<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Services\Services;

class HackGuardPluginReinstall extends ScansBase {

	const SLUG = 'plugin_reinstall';

	protected function exec() {
		$req = Services::Request();

		$activate = $req->post( 'activate' );
		$file = sanitize_text_field( wp_unslash( $req->post( 'file' ) ) );

		if ( $req->post( 'reinstall' ) ) {
			/** @var ModCon $mod */
			$mod = $this->primary_mod;
			/** @var Afs $scan */
			$scan = $mod->getScansCon()->getScanCon( 'afs' );
			$activate = $scan->actionPluginReinstall( $file );
		}

		if ( $activate ) {
			Services::WpPlugins()->activate( $file );
		}

		$this->response()->success = true;
	}
}
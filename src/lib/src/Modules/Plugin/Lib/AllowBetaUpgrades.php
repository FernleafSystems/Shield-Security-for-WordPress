<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Versions;

/**
 * Allows the plugin to access WordPress.org SVN updates/tags that haven't actually been released.
 * This way we can more easily test upgrades to ensure there are no upgrade errors etc. and make it easier for testers.
 */
class AllowBetaUpgrades extends ExecOnceModConsumer {

	use PluginCronsConsumer;

	protected function canRun() :bool {
		$con = $this->getCon();
		return $con->isPremiumActive() && apply_filters( 'shield/enable_beta', false );
	}

	protected function run() {
		add_filter( 'pre_set_site_transient_update_plugins', function ( $updates ) {
			$con = $this->getCon();
			// only offer "betas" when there is no "normal" upgrade already available
			if ( is_object( $updates ) && isset( $updates->response )
				 && is_array( $updates->response ) && empty( $updates->response[ $con->base_file ] ) ) {

				$thisPlugin = Services::WpPlugins()->getPluginAsVo( $con->base_file );
				$versionsLookup = ( new Versions() )->setWorkingSlug( $thisPlugin->slug );
				$versions = $versionsLookup->all();
				$betas = array_filter(
					is_array( $versions ) ? $versions : [],
					function ( $beta ) {
						return preg_match( '#\d\.#', (string)$beta )
							   && version_compare( (string)$beta, $this->getCon()->getVersion(), '>=' );
					}
				);
				if ( !empty( $betas ) ) {
					natsort( $betas );
					$beta = array_pop( $betas );
					$versionsLookup->setWorkingVersion( $beta );
					$url = $versionsLookup->allVersionsUrls()[ $beta ] ?? '';
					if ( !empty( $url ) ) {
						$update = new \stdClass();
						$update->id = $thisPlugin->id;
						$update->slug = $thisPlugin->slug;
						$update->plugin = $con->base_file;
						$update->new_version = $beta;
						$update->package = $url;
						$updates->response[ $con->base_file ] = $update;
					}
				}
			}
			return $updates;
		} );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Versions;

/**
 * Allows the plugin to access WordPress.org SVN updates/tags that haven't actually been released.
 * This way we can more easily test upgrades to ensure there are no upgrade errors etc. and make it easier for testers.
 */
class AllowBetaUpgrades {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	public const MOD = ModCon::SLUG;

	/**
	 * @var \stdClass
	 */
	private $beta;

	protected function canRun() :bool {
		return $this->con()->isPremiumActive()
			   && apply_filters( 'shield/enable_beta', $this->opts()->isOpt( 'enable_beta', 'Y' ) );
	}

	protected function run() {
		add_filter( 'pre_set_site_transient_update_plugins', function ( $updates ) {
			$con = $this->con();
			// only offer "betas" when there is no "normal" upgrade already available
			if ( is_object( $updates )
				 && isset( $updates->response )
				 && is_array( $updates->response )
				 && empty( $updates->response[ $con->base_file ] ) ) {

				$beta = $this->getBeta();
				if ( !empty( $beta ) ) {
					$updates->response[ $con->base_file ] = $beta;
				}
			}
			return $updates;
		} );
	}

	private function getBeta() {
		if ( !isset( $this->beta ) ) {
			$con = $this->con();

			$this->beta = false;

			$thisPlugin = Services::WpPlugins()->getPluginAsVo( $con->base_file );
			$versionsLookup = ( new Versions() )->setWorkingSlug( $thisPlugin->slug );
			$betas = array_filter(
				$versionsLookup->all(),
				function ( $betaVersion ) {
					return is_string( $betaVersion )
						   && preg_match( '#^\d+(\.\d+)+$#', $betaVersion )
						   && version_compare( $betaVersion, $this->con()->getVersion(), '>' );
				}
			);
			if ( !empty( $betas ) ) {
				natsort( $betas );
				$beta = array_pop( $betas );
				$versionsLookup->setWorkingVersion( $beta );
				$url = $versionsLookup->allVersionsUrls()[ $beta ] ?? '';
				if ( !empty( $url ) ) {
					$this->beta = new \stdClass();
					$this->beta->id = $thisPlugin->id;
					$this->beta->slug = $thisPlugin->slug;
					$this->beta->plugin = $con->base_file;
					$this->beta->new_version = $beta;
					$this->beta->package = $url;
					$this->beta->icons = [
						'2x' => sprintf( 'https://ps.w.org/%s/assets/icon-256x256.png', $thisPlugin->slug ),
						'1x' => sprintf( 'https://ps.w.org/%s/assets/icon-128x128.png', $thisPlugin->slug ),
					];
				}
			}
		}

		return $this->beta;
	}
}
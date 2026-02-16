<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Versions;

/**
 * Allows the plugin to access WordPress.org SVN updates/tags that haven't actually been released.
 * This way we can more easily test upgrades to ensure there are no upgrade errors etc. and make it easier for testers.
 */
class AllowBetaUpgrades {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var false|\stdClass
	 */
	private $beta;

	protected function canRun() :bool {
		return self::con()->isPremiumActive();
	}

	protected function run() {
		add_filter( 'site_transient_update_plugins', fn( $updates ) => $this->removeStaleSelfUpdateNotice( $updates ) );
		add_filter( 'pre_set_site_transient_update_plugins', function ( $updates ) {
			$updates = $this->removeStaleSelfUpdateNotice( $updates );

			// only offer "betas" when there is no "normal" upgrade already available
			if ( $this->isBetaEnabled()
				 && \is_object( $updates )
				 && isset( $updates->response )
				 && \is_array( $updates->response )
				 && empty( $updates->response[ self::con()->base_file ] ) ) {

				if ( !empty( $this->getBeta() ) ) {
					$updates->response[ self::con()->base_file ] = $this->getBeta();
				}
			}
			return $updates;
		} );
	}

	/**
	 * Some update checks leave stale entries in "response" for the current plugin version.
	 * Since update availability checks often only test response-entry existence, strip stale self-updates here.
	 *
	 * @param \stdClass|mixed $updates
	 * @return \stdClass|mixed
	 */
	private function removeStaleSelfUpdateNotice( $updates ) {
		return $this->removeStaleSelfUpdateNoticeCore(
			$updates,
			self::con()->base_file,
			self::con()->cfg->version()
		);
	}

	/**
	 * @param \stdClass|mixed $updates
	 * @return \stdClass|mixed
	 */
	private function removeStaleSelfUpdateNoticeCore( $updates, string $baseFile, string $currentVersion ) {
		if ( \is_object( $updates )
			 && !empty( $baseFile )
			 && !empty( $currentVersion )
			 && isset( $updates->response )
			 && \is_array( $updates->response )
			 && !empty( $updates->response[ $baseFile ] ) ) {

			$ourUpdate = $updates->response[ $baseFile ];
			$ourUpdate = \is_array( $ourUpdate ) ? (object)$ourUpdate : $ourUpdate;

			$newVersion = \is_object( $ourUpdate ) ? (string)( $ourUpdate->new_version ?? '' ) : '';
			if ( !empty( $newVersion ) && \version_compare( $newVersion, $currentVersion, '<=' ) ) {
				unset( $updates->response[ $baseFile ] );
			}
		}

		return $updates;
	}

	private function isBetaEnabled() :bool {
		return apply_filters( 'shield/enable_beta', self::con()->opts->optIs( 'enable_beta', 'Y' ) );
	}

	private function getBeta() {
		if ( !isset( $this->beta ) ) {

			$this->beta = false;

			$thisPlugin = Services::WpPlugins()->getPluginAsVo( self::con()->base_file );
			$versionsLookup = ( new Versions() )->setWorkingSlug( $thisPlugin->slug );
			$betas = \array_filter(
				$versionsLookup->all(),
				fn( $betaVersion ) => \is_string( $betaVersion )
									  && \preg_match( '#^\d+(\.\d+)+$#', $betaVersion )
									  && \version_compare( $betaVersion, self::con()->cfg->version(), '>' )
			);
			if ( !empty( $betas ) ) {
				\natsort( $betas );
				$beta = \array_pop( $betas );
				$versionsLookup->setWorkingVersion( $beta );
				$url = $versionsLookup->allVersionsUrls()[ $beta ] ?? '';
				if ( !empty( $url ) ) {
					$this->beta = new \stdClass();
					$this->beta->id = $thisPlugin->id;
					$this->beta->slug = $thisPlugin->slug;
					$this->beta->plugin = self::con()->base_file;
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\WordPressOrg\PluginVersions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Allows the plugin to access WordPress.org SVN updates/tags that haven't actually been released.
 * This way we can more easily test upgrades to ensure there are no upgrade errors etc. and make it easier for testers.
 */
class AllowBetaUpgrades {
	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var null|false|\stdClass
	 */
	private $beta = null;

	protected function canRun(): bool {
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
		$baseFile = \trim( $baseFile );
		$currentVersion = PluginVersions::normalizeReleaseVersion( $currentVersion );

		if ( \is_object( $updates )
		     && $baseFile !== ''
		     && $currentVersion !== ''
		     && isset( $updates->response )
		     && \is_array( $updates->response )
		     && !empty( $updates->response[ $baseFile ] ) ) {

			$ourUpdate = $updates->response[ $baseFile ];
			$ourUpdate = \is_array( $ourUpdate ) ? (object)$ourUpdate : $ourUpdate;

			$newVersionRaw = \is_object( $ourUpdate ) ? ( $ourUpdate->new_version ?? '' ) : '';
			$newVersion = PluginVersions::normalizeReleaseVersion( $newVersionRaw );
			if ( $newVersion !== '' && \version_compare( $newVersion, $currentVersion, '<=' ) ) {
				unset( $updates->response[ $baseFile ] );
			}
		}

		return $updates;
	}

	private function isBetaEnabled(): bool {
		return apply_filters( 'shield/enable_beta', self::con()->opts->optIs( 'enable_beta', 'Y' ) );
	}

	private function getBeta() {
		if ( !isset( $this->beta ) ) {

			$this->beta = false;

			$thisPlugin = Services::WpPlugins()->getPluginAsVo( self::con()->base_file );

			if ( $thisPlugin instanceof WpPluginVo ) {
				$slugRaw = $thisPlugin->slug;
				$slug = \is_scalar( $slugRaw ) ? \trim( (string)$slugRaw ) : '';
				$versionsLookup = new PluginVersions( $slug );
				$beta = $versionsLookup->latestVersionNewerThan( self::con()->cfg->version() );
				$url = $beta === null ? '' : $versionsLookup->urlForVersion( $beta );
				if ( $url !== '' ) {
					$idRaw = $thisPlugin->id;
					$this->beta = new \stdClass();
					$this->beta->id = \is_scalar( $idRaw ) ? (string)$idRaw : '';
					$this->beta->slug = $slug;
					$this->beta->plugin = self::con()->base_file;
					$this->beta->new_version = $beta;
					$this->beta->package = $url;
					$this->beta->icons = [
						'2x' => sprintf( 'https://ps.w.org/%s/assets/icon-256x256.png', $slug ),
						'1x' => sprintf( 'https://ps.w.org/%s/assets/icon-128x128.png', $slug ),
					];
				}
			}
		}

		return $this->beta;
	}
}

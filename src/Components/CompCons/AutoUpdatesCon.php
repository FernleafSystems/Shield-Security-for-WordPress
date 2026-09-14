<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AutoUpdatesCon {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * CP done gone and messed about with automatic updates, so we don't even consider supporting their filters.
	 */
	protected function canRun() {
		return !Services::WpGeneral()->isClassicPress();
	}

	/**
	 * The allow_* core filters are run first in a "should_update" query. Then comes the "auto_update_core"
	 * filter. What this filter decides will ultimately determine the fate of any core upgrade.
	 */
	protected function run() {
		$priority = (int)self::con()->cfg->configuration->def( 'action_hook_priority' );

		add_filter( 'auto_update_plugin', [ $this, 'autoupdate_plugins' ], $priority, 2 );
		add_filter( 'auto_update_theme', [ $this, 'autoupdate_themes' ], $priority, 2 );
		add_filter( 'auto_update_core', [ $this, 'autoupdate_core' ], $priority, 2 );

		add_filter( 'auto_core_update_email', [ $this, 'autoupdate_email_override' ], $priority );
		add_filter( 'auto_plugin_theme_update_email', [ $this, 'autoupdate_email_override' ], $priority );
		add_action( 'set_site_transient_update_core', [ $this, 'trackUpdateTimesCore' ] );
		add_action( 'set_site_transient_update_plugins', [ $this, 'trackUpdateTimesPlugins' ] );
		add_action( 'set_site_transient_update_themes', [ $this, 'trackUpdateTimesThemes' ] );

		add_filter( 'plugins_list', [ $this, 'indicateAutoUpdate' ] );
	}

	/**
	 * Indicate on the plugins table that the plugin is set to automatically update based on the plugin's config
	 * (and regardless of any delays).
	 * @param array[]|mixed $plugins
	 */
	public function indicateAutoUpdate( $plugins ) :array {
		if ( !\is_array( $plugins ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $plugins as $sectionKey => $section ) {
			if ( !\is_array( $section ) ) {
				continue;
			}

			$rows = \array_filter( $section, '\is_array' );
			if ( isset( $rows[ self::con()->base_file ] ) ) {
				$rows[ self::con()->base_file ][ 'auto-update-forced' ] = self::con()->opts->optGet( 'autoupdate_plugin_self' ) !== 'disabled';
			}
			$normalized[ $sectionKey ] = $rows;
		}

		return $normalized;
	}

	/**
	 * @param \stdClass|mixed $updates
	 */
	public function trackUpdateTimesCore( $updates ) {

		if ( !empty( $updates ) && isset( $updates->updates ) && \is_array( $updates->updates ) ) {

			$delayTracking = $this->getDelayTracking();

			$item = $delayTracking[ 'core' ][ 'wp' ] ?? [];
			foreach ( $updates->updates as $upd ) {
				if ( \is_array( $upd ) ) {
					$upd = (object)$upd;
				}
				if ( \is_object( $upd ) && 'autoupdate' === ( $upd->response ?? null ) ) {
					$version = $this->normalizeNonEmptyString( $upd->current ?? null );
					if ( $version !== null && !isset( $item[ $version ] ) ) {
						$item[ $version ] = Services::Request()->ts();
					}
				}
			}
			$delayTracking[ 'core' ][ 'wp' ] = \array_slice( $item, -5, null, true );

			self::con()->opts->optSet( 'delay_tracking', $delayTracking );
		}
	}

	/**
	 * @param \stdClass|mixed $updates
	 */
	public function trackUpdateTimesPlugins( $updates ) {
		$this->trackUpdateTimeCommon( $updates, 'plugins' );
	}

	/**
	 * @param \stdClass|mixed $updates
	 */
	public function trackUpdateTimesThemes( $updates ) {
		$this->trackUpdateTimeCommon( $updates, 'themes' );
	}

	/**
	 * Context is either 'plugins' or 'themes'
	 * @param \stdClass|mixed $updates
	 */
	protected function trackUpdateTimeCommon( $updates, string $context ) {
		if ( !empty( $updates ) && isset( $updates->response ) && \is_array( $updates->response ) ) {
			$delayTracking = $this->getDelayTracking();

			foreach ( $updates->response as $slugRaw => $theUpdate ) {
				$slug = $this->normalizeLogicalStringMapKey( $slugRaw );
				if ( $slug === null ) {
					continue;
				}

				$itemTrack = $delayTracking[ $context ][ $slug ] ?? [];
				if ( \is_array( $theUpdate ) ) {
					$theUpdate = (object)$theUpdate;
				}

				$newVersion = \is_object( $theUpdate ) ? $this->normalizeNonEmptyString( $theUpdate->new_version ?? null ) : null;
				if ( $newVersion !== null ) {
					if ( !isset( $itemTrack[ $newVersion ] ) ) {
						$itemTrack[ $newVersion ] = Services::Request()->ts();
					}
					$delayTracking[ $context ][ $slug ] = \array_slice( $itemTrack, -3, null, true );
				}
			}

			self::con()->opts->optSet( 'delay_tracking', $delayTracking );
		}
	}

	/**
	 * @param bool|mixed      $autoupdate
	 * @param \stdClass|mixed $coreUpgrade
	 * @return bool|mixed
	 */
	public function autoupdate_core( $autoupdate, $coreUpgrade ) {
		$version = \is_object( $coreUpgrade ) ? $this->normalizeNonEmptyString( $coreUpgrade->current ?? null ) : null;
		return $version !== null && $this->isDelayed( 'core', 'wp', $version ) ? false : $autoupdate;
	}

	/**
	 * @param bool|mixed       $autoupdate
	 * @param \stdClass|string $item
	 * @return bool|mixed
	 */
	public function autoupdate_plugins( $autoupdate, $item ) {

		if ( \is_object( $item ) ) {
			$pluginFile = $this->normalizeNonEmptyString( $item->plugin ?? null );
			if ( $pluginFile === null ) {
				return $autoupdate;
			}

			$con = self::con();
			if ( $pluginFile === $con->base_file ) {
				$auto = $con->opts->optGet( 'autoupdate_plugin_self' );
				if ( $auto === 'disabled' ) {
					return false;
				}

				$version = $this->pluginUpdateVersion( $pluginFile );
				if ( $version === null ) {
					return $autoupdate;
				}
				$autoupdate = $auto === 'immediate' || !$this->isDelayed( 'plugins', $pluginFile, $version );
			}
			else {
				$version = $this->pluginUpdateVersion( $pluginFile );
				if ( $version === null ) {
					return $autoupdate;
				}

				$WPV = $con->comps->scans->WPV();
				if ( $WPV->isAutoupdatesEnabled() && $WPV->hasVulnerabilities( $pluginFile ) ) {
					$autoupdate = true;
				}
				elseif ( $this->isDelayed( 'plugins', $pluginFile, $version ) ) {
					$autoupdate = false;
				}
			}
		}

		return $autoupdate;
	}

	/**
	 * @param bool|mixed      $autoupdate
	 * @param \stdClass|mixed $item
	 * @return bool|mixed
	 */
	public function autoupdate_themes( $autoupdate, $item ) {
		$slug = \is_object( $item ) ? $this->normalizeNonEmptyString( $item->theme ?? null ) : null;
		if ( $slug === null ) {
			return $autoupdate;
		}

		$themeInfo = Services::WpThemes()->getUpdateInfo( $slug );
		$version = \is_array( $themeInfo ) ? $this->normalizeNonEmptyString( $themeInfo[ 'new_version' ] ?? null ) : null;
		return $version !== null && $this->isDelayed( 'themes', $slug, $version ) ? false : $autoupdate;
	}

	private function isDelayed( string $context, string $slug, string $version ) :bool {
		$delayed = false;
		$con = self::con();

		$delayRaw = $con->opts->optGet( 'update_delay' );
		$delay = \is_int( $delayRaw ) ? \max( 0, $delayRaw ) : 0;
		$isSelfPlugin = $context === 'plugins' && $slug === $con->base_file;
		if ( $isSelfPlugin ) {
			$selfDelay = $con->cfg->properties[ 'autoupdate_days' ] ?? 0;
			$delay = \max( $delay, \is_int( $selfDelay ) ? $selfDelay : 0 );
		}
		if ( $delay > 0 ) {
			$delayTracking = $this->getDelayTracking();
			$track = $delayTracking[ $context ][ $slug ] ?? [];
			if ( $isSelfPlugin && !isset( $track[ $version ] ) ) {
				$track[ $version ] = Services::Request()->ts();
				$delayTracking[ $context ][ $slug ] = \array_slice( $track, -3, null, true );
				$con->opts->optSet( 'delay_tracking', $delayTracking );
			}

			$delayed = isset( $track[ $version ] )
					   && ( Services::Request()->ts() - $track[ $version ] ) < $delay*DAY_IN_SECONDS;
		}

		return $delayed;
	}

	/**
	 * A filter on the target email address to which to send upgrade notification emails.
	 * @param array|mixed $emailParams
	 * @return array|mixed
	 */
	public function autoupdate_email_override( $emailParams ) {
		if ( \is_array( $emailParams ) ) {
			$emailParams[ 'to' ] = self::con()->comps->opts_lookup->getReportEmail();
		}
		return $emailParams;
	}

	public function getDelayTracking() :array {
		$opts = self::con()->opts;
		$stored = $opts->optGet( 'delay_tracking' );
		$normalized = [
			'core'    => [],
			'plugins' => [],
			'themes'  => [],
		];
		if ( \is_array( $stored ) ) {
			foreach ( $normalized as $context => $_ ) {
				$contextItems = $stored[ $context ] ?? null;
				if ( !\is_array( $contextItems ) ) {
					continue;
				}
				foreach ( $contextItems as $slugRaw => $versions ) {
					$slug = $this->normalizeLogicalStringMapKey( $slugRaw );
					if ( $slug === null || ( $context === 'core' && $slug !== 'wp' ) || !\is_array( $versions ) ) {
						continue;
					}
					foreach ( $versions as $versionRaw => $timestamp ) {
						$version = $this->normalizeLogicalStringMapKey( $versionRaw );
						if ( $version !== null && \is_int( $timestamp ) && $timestamp > 0 ) {
							$normalized[ $context ][ $slug ][ $version ] = $timestamp;
						}
					}
				}
			}
		}

		$opts->optSet( 'delay_tracking', $normalized );
		return $normalized;
	}

	private function pluginUpdateVersion( string $pluginFile ) :?string {
		$pluginInfo = Services::WpPlugins()->getUpdateInfo( $pluginFile );
		return \is_object( $pluginInfo ) ? $this->normalizeNonEmptyString( $pluginInfo->new_version ?? null ) : null;
	}

	private function normalizeLogicalStringMapKey( $value ) :?string {
		return $this->normalizeNonEmptyString( \is_int( $value ) ? (string)$value : $value );
	}

	private function normalizeNonEmptyString( $value ) :?string {
		if ( !\is_string( $value ) ) {
			return null;
		}
		$value = \trim( $value );
		return $value === '' ? null : $value;
	}
}

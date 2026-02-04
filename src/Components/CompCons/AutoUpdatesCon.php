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
		return \array_map(
			function ( $section ) {
				if ( isset( $section[ self::con()->base_file ] ) ) {
					$section[ self::con()->base_file ][ 'auto-update-forced' ] = self::con()->opts->optGet( 'autoupdate_plugin_self' ) !== 'disabled';
				}
				return $section;
			},
			\is_array( $plugins ) ? $plugins : []
		);
	}

	/**
	 * @param \stdClass|mixed $updates
	 */
	public function trackUpdateTimesCore( $updates ) {

		if ( !empty( $updates ) && isset( $updates->updates ) && \is_array( $updates->updates ) ) {

			$delayTracking = $this->getDelayTracking();

			$item = $delayTracking[ 'core' ][ 'wp' ] ?? [];
			foreach ( $updates->updates as $upd ) {
				if ( 'autoupdate' == $upd->response ) {
					$version = $upd->current;
					if ( !isset( $item[ $version ] ) ) {
						$item[ $version ] = Services::Request()->ts();
					}
				}
			}
			$delayTracking[ 'core' ][ 'wp' ] = \array_slice( $item, -5 );

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

			foreach ( $updates->response as $slug => $theUpdate ) {
				$itemTrack = $delayTracking[ $context ][ $slug ] ?? [];
				if ( \is_array( $theUpdate ) ) {
					$theUpdate = (object)$theUpdate;
				}

				$newVersion = $theUpdate->new_version ?? '';
				if ( !empty( $newVersion ) ) {
					if ( !isset( $itemTrack[ $newVersion ] ) ) {
						$itemTrack[ $newVersion ] = Services::Request()->ts();
					}
					$delayTracking[ $context ][ $slug ] = \array_slice( $itemTrack, -3 );
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
		return $this->isDelayed( $coreUpgrade, 'core' ) ? false : $autoupdate;
	}

	/**
	 * @param bool|mixed       $autoupdate
	 * @param \stdClass|string $item
	 * @return bool|mixed
	 */
	public function autoupdate_plugins( $autoupdate, $item ) {

		if ( \is_object( $item ) && !empty( $item->plugin ) ) {
			$con = self::con();
			$WPV = $con->comps->scans->WPV();
			if ( $WPV->isAutoupdatesEnabled() && $WPV->hasVulnerabilities( $item->plugin ) ) {
				$autoupdate = true;
			}
			elseif ( $item->plugin === $con->base_file ) {
				$auto = $con->opts->optGet( 'autoupdate_plugin_self' );
				$autoupdate = $auto !== 'disabled'
							  && ( $auto === 'immediate' || !$this->isDelayed( $item->plugin, 'plugins' ) );
			}
			elseif ( $this->isDelayed( $item->plugin, 'plugins' ) ) {
				$autoupdate = false;
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
		return ( \is_object( $item ) && !empty( $item->theme ) && $this->isDelayed( $item->theme, 'themes' ) ) ? false : $autoupdate;
	}

	/**
	 * @param string|\stdClass $slug
	 */
	private function isDelayed( $slug, string $context ) :bool {
		$delayed = false;

		$delay = self::con()->opts->optGet( 'update_delay' );
		if ( $delay > 0 ) {

			$version = '';
			if ( $context === 'core' ) {
				$version = $slug->current; // \stdClass from transient update_core
				$slug = 'wp';
			}

			if ( $context == 'plugins' ) {
				$pluginInfo = Services::WpPlugins()->getUpdateInfo( $slug );
				$version = $pluginInfo->new_version ?? '';
				if ( $slug === self::con()->base_file ) {
					$delay = \max( $delay, self::con()->cfg->properties[ 'autoupdate_days' ] );
				}
			}
			elseif ( $context == 'themes' ) {
				$themeInfo = Services::WpThemes()->getUpdateInfo( $slug );
				$version = $themeInfo[ 'new_version' ] ?? '';
			}

			$track = $this->getDelayTracking()[ $context ][ $slug ] ?? [];
			$delayed = !empty( $version )
					   && isset( $track[ $version ] )
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
		// @deprecated 19.2 - isset() required for upgrade from 19.0
		if ( \is_array( $emailParams ) && !\is_null( self::con()->comps ) && !\is_null( self::con()->comps->opts_lookup ) ) {
			$emailParams[ 'to' ] = self::con()->comps->opts_lookup->getReportEmail();
		}
		return $emailParams;
	}

	public function getDelayTracking() :array {
		$opts = self::con()->opts;

		$opts->optSet( 'delay_tracking',
			Services::DataManipulation()->mergeArraysRecursive( [
				'core'    => [],
				'plugins' => [],
				'themes'  => [],
			], $opts->optGet( 'delay_tracking' ) )
		);

		return $opts->optGet( 'delay_tracking' );
	}

	/**
	 * @deprecated 20.1
	 */
	public function isDelayUpdates() :bool {
		return self::con()->opts->optGet( 'update_delay' ) > 0;
	}
}
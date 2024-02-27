<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AdjustAuto {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		add_filter( 'auto_update_plugin', [ $this, 'onWpAutoUpdate' ], 500, 2 );
	}

	/**
	 * This is a filter method designed to say whether WordPress plugin upgrades should be permitted,
	 * based on the plugin settings.
	 * @param bool          $isAutoUpdate
	 * @param string|object $mItem
	 * @return bool
	 */
	public function onWpAutoUpdate( $isAutoUpdate, $mItem ) {
		$con = self::con();
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();

		$file = $WP->getFileFromAutomaticUpdateItem( $mItem );

		// The item in question is this plugin...
		if ( $file === $con->base_file ) {
			$autoUpdateSelf = $con->cfg->properties[ 'autoupdate' ];

			if ( !$WP->isRunningAutomaticUpdates() && $autoUpdateSelf == 'confidence' ) {
				$autoUpdateSelf = 'yes'; // so that we appear to be automatically updating
			}

			$new = $WPP->getUpdateNewVersion( $file );

			switch ( $autoUpdateSelf ) {
				case 'yes' :
					$isAutoUpdate = true;
					break;
				case 'block' :
					$isAutoUpdate = false;
					break;
				case 'confidence' :
					$isAutoUpdate = false;
					if ( !empty( $new ) ) {
						$firstDetected = $con->cfg->update_first_detected[ $new ] ?? 0;
						$availableFor = Services::Request()->ts() - $firstDetected;
						$isAutoUpdate = $firstDetected > 0
										&& $availableFor > \DAY_IN_SECONDS*$con->cfg->properties[ 'autoupdate_days' ];
					}
					break;
				case 'pass' :
				default:
					break;
			}
		}
		return $isAutoUpdate;
	}
}
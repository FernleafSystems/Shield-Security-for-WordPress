<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {
		$aResponse = [];

		switch ( $sAction ) {
			case 'toggle_plugin_autoupdate':
				$aResponse = $this->ajaxExec_TogglePluginAutoupdate();
				break;
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	public function ajaxExec_TogglePluginAutoupdate() {
		/** @var \ICWP_WPSF_FeatureHandler_Autoupdates $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;
		$sMessage = __( 'You do not have permissions to perform this action.', 'wp-simple-firewall' );

		if ( $oMod->isAutoupdateIndividualPlugins() && $this->getCon()->isPluginAdmin() ) {
			$oWpPlugins = Services::WpPlugins();
			$sFile = Services::Request()->post( 'pluginfile' );
			if ( $oWpPlugins->isInstalled( $sFile ) ) {
				$oMod->setPluginToAutoUpdate( $sFile );

				$sMessage = sprintf( __( 'Plugin "%s" will %s.', 'wp-simple-firewall' ),
					$oWpPlugins->getPluginAsVo( $sFile )->Name,
					Services::WpPlugins()->isPluginAutomaticallyUpdated( $sFile ) ?
						__( 'update automatically', 'wp-simple-firewall' )
						: __( 'not update automatically', 'wp-simple-firewall' )
				);
				$bSuccess = true;
			}
			else {
				$sMessage = __( 'Failed to change the update status of the plugin.', 'wp-simple-firewall' );
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}
}
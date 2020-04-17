<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		/** @var Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$aNotices = [
			'title'    => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'messages' => []
		];
		{ //really disabled?
			$oWp = Services::WpGeneral();
			if ( $this->isModOptEnabled() ) {
				if ( $oOpts->isDisableAllAutoUpdates() && !$oWp->getWpAutomaticUpdater()->is_disabled() ) {
					$aNotices[ 'messages' ][ 'disabled_auto' ] = [
						'title'   => 'Auto Updates Not Really Disabled',
						'message' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
						'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
						'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
						'rec'     => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																							->getHumanName() )
					];
				}
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );

		$aAllNotices[ 'autoupdates' ] = $aNotices;
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		/** @var Autoupdates\Options $oOpts */
		$oOpts = $this->getOptions();

		$aThis = [
			'strings'      => [
				'title' => __( 'Automatic Updates', 'wp-simple-firewall' ),
				'sub'   => __( 'Control WordPress Automatic Updates', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isModOptEnabled() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {

			$bAllDisabled = $oOpts->isDisableAllAutoUpdates();
			if ( $bAllDisabled ) {
				$aThis[ 'key_opts' ][ 'disabled' ] = [
					'name'    => __( 'Disabled All', 'wp-simple-firewall' ),
					'enabled' => !$bAllDisabled,
					'summary' => $bAllDisabled ?
						__( 'All automatic updates on this site are disabled', 'wp-simple-firewall' )
						: __( 'The automatic updates system is enabled', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
				];
			}
			else {
				$bCanCore = Services::WpGeneral()->canCoreUpdateAutomatically();
				$aThis[ 'key_opts' ][ 'core_minor' ] = [
					'name'    => __( 'Core Updates', 'wp-simple-firewall' ),
					'enabled' => $bCanCore,
					'summary' => $bCanCore ?
						__( 'Minor WP Core updates will be installed automatically', 'wp-simple-firewall' )
						: __( 'Minor WP Core updates will not be installed automatically', 'wp-simple-firewall' ),
					'weight'  => 2,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_core' ),
				];

				$bHasDelay = $this->isModOptEnabled() && $oOpts->getDelayUpdatesPeriod();
				$aThis[ 'key_opts' ][ 'delay' ] = [
					'name'    => __( 'Update Delay', 'wp-simple-firewall' ),
					'enabled' => $bHasDelay,
					'summary' => $bHasDelay ?
						__( 'Automatic updates are applied after a short delay', 'wp-simple-firewall' )
						: __( 'Automatic updates are applied immediately', 'wp-simple-firewall' ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'update_delay' ),
				];

				$sName = $this->getCon()->getHumanName();
				$bSelfAuto = $this->isModOptEnabled()
							 && in_array( $oOpts->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
				$aThis[ 'key_opts' ][ 'self' ] = [
					'name'    => __( 'Self Auto-Update', 'wp-simple-firewall' ),
					'enabled' => $bSelfAuto,
					'summary' => $bSelfAuto ?
						sprintf( __( '%s is automatically updated', 'wp-simple-firewall' ), $sName )
						: sprintf( __( "%s isn't automatically updated", 'wp-simple-firewall' ), $sName ),
					'weight'  => 1,
					'href'    => $this->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
				];
			}
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Autoupdates';
	}
}
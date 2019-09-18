<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Autoupdates extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 */
	protected function setupCustomHooks() {
		parent::setupCustomHooks();
		// Force run automatic updates
		if ( Services::Request()->query( 'force_run_auto_updates' ) == 'now' ) {
			add_filter( $this->prefix( 'force_autoupdate' ), '__return_true' );
		}
	}

	/**
	 * @return string[]
	 */
	public function getAutoupdatePlugins() {
		$aSelected = [];
		if ( $this->isAutoupdateIndividualPlugins() ) {
			$aSelected = $this->getOpt( 'selected_plugins', [] );
			if ( !is_array( $aSelected ) ) {
				$aSelected = [];
			}
		}
		return $aSelected;
	}

	/**
	 * @return array
	 */
	public function getDelayTracking() {
		$aTracking = $this->getOpt( 'delay_tracking', [] );
		if ( !is_array( $aTracking ) ) {
			$aTracking = [];
		}
		$aTracking = Services::DataManipulation()->mergeArraysRecursive(
			[
				'core'    => [],
				'plugins' => [],
				'themes'  => [],
			],
			$aTracking
		);
		$this->setOpt( 'delay_tracking', $aTracking );

		return $aTracking;
	}

	/**
	 * @return int
	 */
	public function getDelayUpdatesPeriod() {
		return $this->isPremium() ? $this->getOpt( 'update_delay', 0 )*DAY_IN_SECONDS : 0;
	}

	/**
	 * @param array $aTrackingInfo
	 * @return $this
	 */
	public function setDelayTracking( $aTrackingInfo ) {
		return $this->setOpt( 'delay_tracking', $aTrackingInfo );
	}

	/**
	 * @return bool
	 */
	public function isDisableAllAutoUpdates() {
		return $this->isOpt( 'enable_autoupdate_disable_all', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isAutoupdateAllPlugins() {
		return $this->isOpt( 'enable_autoupdate_plugins', 'Y' );
	}

	/**
	 * @premium
	 * @return bool
	 */
	public function isAutoupdateIndividualPlugins() {
		return $this->isOpt( 'enable_individual_autoupdate_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isDelayUpdates() {
		return $this->getDelayUpdatesPeriod() > 0;
	}

	/**
	 * @param string $sPluginFile
	 * @return bool
	 */
	public function isPluginSetToAutoupdate( $sPluginFile ) {
		return in_array( $sPluginFile, $this->getAutoupdatePlugins() );
	}

	/**
	 * @return bool
	 */
	public function isSendAutoupdatesNotificationEmail() {
		return $this->isOpt( 'enable_upgrade_notification_email', 'Y' );
	}

	/**
	 * @return string
	 */
	public function getSelfAutoUpdateOpt() {
		return $this->getOpt( 'autoupdate_plugin_self' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMinor() {
		return !$this->isOpt( 'autoupdate_core', 'core_never' );
	}

	/**
	 * @return bool
	 */
	public function isAutoUpdateCoreMajor() {
		return $this->isOpt( 'autoupdate_core', 'core_major' );
	}

	/**
	 * @param string $sPluginFile
	 * @return $this
	 */
	public function setPluginToAutoUpdate( $sPluginFile ) {
		$aPlugins = $this->getAutoupdatePlugins();
		$nKey = array_search( $sPluginFile, $aPlugins );

		if ( $nKey === false ) {
			$aPlugins[] = $sPluginFile;
		}
		else {
			unset( $aPlugins[ $nKey ] );
		}

		return $this->setOpt( 'selected_plugins', $aPlugins );
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		$aNotices = [
			'title'    => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'messages' => []
		];
		{ //really disabled?
			$oWp = Services::WpGeneral();
			if ( $this->isModOptEnabled() ) {
				if ( $this->isDisableAllAutoUpdates() && !$oWp->getWpAutomaticUpdater()->is_disabled() ) {
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

			$bAllDisabled = $this->isDisableAllAutoUpdates();
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

				$bHasDelay = $this->isModOptEnabled() && $this->getDelayUpdatesPeriod();
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
							 && in_array( $this->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
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
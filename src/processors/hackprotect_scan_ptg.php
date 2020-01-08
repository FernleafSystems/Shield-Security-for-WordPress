<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services;

class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ptg';

	/**
	 */
	public function run() {
		parent::run();

		// init snapshots and build as necessary
		( new HackGuard\Lib\Snapshots\StoreAction\BuildAll() )
			->setMod( $this->getMod() )
			->build();

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isPtgReinstallLinks() ) {
			add_filter( 'plugin_action_links', [ $this, 'addActionLinkRefresh' ], 50, 2 );
			add_action( 'admin_footer', [ $this, 'printPluginReinstallDialogs' ] );
		}
	}

	/**
	 * @param array  $aLinks
	 * @param string $sPluginFile
	 * @return string[]
	 */
	public function addActionLinkRefresh( $aLinks, $sPluginFile ) {
		$oWpP = Services\Services::WpPlugins();

		$oPlgn = $oWpP->getPluginAsVo( $sPluginFile );
		if ( $oPlgn instanceof Services\Core\VOs\WpPluginVo && $oPlgn->isWpOrg() && !$oWpP->isUpdateAvailable( $sPluginFile ) ) {
			$sLinkTemplate = '<a href="javascript:void(0)">%s</a>';
			$aLinks[ 'icwp-reinstall' ] = sprintf(
				$sLinkTemplate,
				__( 'Re-Install', 'wp-simple-firewall' )
			);
		}

		return $aLinks;
	}

	/**
	 * @param string $sItem
	 * @return array|null
	 */
	public function getSnapshotItemMeta( $sItem ) {
		try {
			$aMeta = ( new HackGuard\Lib\Snapshots\StoreAction\Load() )
				->setMod( $this->getMod() )
				->setAsset( $this->getAssetFromSlug( $sItem ) )
				->run()
				->getSnapMeta();
		}
		catch ( Exception $oE ) {
			$aMeta = null;
		}
		return $aMeta;
	}

	/**
	 * @param string $sSlug
	 * @return Services\Core\VOs\WpPluginVo|Services\Core\VOs\WpThemeVo|null
	 */
	protected function getAssetFromSlug( $sSlug ) {
		if ( Services\Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$oAsset = Services\Services::WpPlugins()->getPluginAsVo( $sSlug );
		}
		elseif ( Services\Services::WpThemes()->isInstalled( $sSlug ) ) {
			$oAsset = Services\Services::WpThemes()->getThemeAsVo( $sSlug );
		}
		return $oAsset;
	}

	public function printPluginReinstallDialogs() {
		echo $this->getMod()->renderTemplate(
			'snippets/dialog_plugins_reinstall.twig',
			[
				'strings'     => [
					'are_you_sure'       => __( 'Are you sure?', 'wp-simple-firewll' ),
					'really_reinstall'   => __( 'Really Re-Install Plugin', 'wp-simple-firewll' ),
					'wp_reinstall'       => __( 'WordPress will now download and install the latest available version of this plugin.', 'wp-simple-firewll' ),
					'in_case'            => sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						__( 'In case of possible failure, it may be better to do this while the plugin is inactive.', 'wp-simple-firewll' )
					),
					'reinstall_first'    => __( 'Re-install first?', 'wp-simple-firewall' ),
					'corrupted'          => __( "This ensures files for this plugin haven't been corrupted in any way.", 'wp-simple-firewall' ),
					'choose'             => __( "You can choose to 'Activate Only' (not recommended), or close this message to cancel activation.", 'wp-simple-firewall' ),
					'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
					'download'           => sprintf(
						__( 'For best security practices, %s will download and re-install the latest available version of this plugin.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					)
				],
				'js_snippets' => []
			],
			true
		);
	}

	/**
	 * @param Shield\Scans\Ptg\ResultsSet $oRes
	 * @return bool
	 */
	protected function runCronUserNotify( $oRes ) {
		$this->emailResults( $oRes );
		return true;
	}

	/**
	 * @param Shield\Scans\Ptg\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oWpPlugins = Services\Services::WpPlugins();
		$oWpThemes = Services\Services::WpThemes();

		$aAllPlugins = [];
		foreach ( $oRes->getResultsForPluginsContext()->getUniqueSlugs() as $sBaseFile ) {
			$oP = $oWpPlugins->getPluginAsVo( $sBaseFile );
			if ( !empty( $oP ) ) {
				$sVersion = empty( $oP->Version ) ? '' : ': v'.ltrim( $oP->Version, 'v' );
				$aAllPlugins[] = sprintf( '%s%s', $oP->Name, $sVersion );
			}
		}

		$aAllThemes = [];
		foreach ( $oRes->getResultsForThemesContext()->getUniqueSlugs() as $sBaseFile ) {
			$oTheme = $oWpThemes->getTheme( $sBaseFile );
			if ( !empty( $oTheme ) ) {
				$sVersion = empty( $oTheme->version ) ? '' : ': v'.ltrim( $oTheme->version, 'v' );
				$aAllThemes[] = sprintf( '%s%s', $oTheme->get( 'Name' ), $sVersion );
			}
		}

		$sName = $this->getCon()->getHumanName();
		$sHomeUrl = Services\Services::WpGeneral()->getHomeUrl();

		$aContent = [
			sprintf( __( '%s has detected at least 1 Plugins/Themes have been modified on your site.', 'wp-simple-firewall' ), $sName ),
			'',
			sprintf( '<strong>%s</strong>', __( 'You will receive only 1 email notification about these changes in a 1 week period.', 'wp-simple-firewall' ) ),
			'',
			sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			'',
			__( 'Details of the problem items are below:', 'wp-simple-firewall' ),
		];

		if ( !empty( $aAllPlugins ) ) {
			$aContent[] = '';
			$aContent[] = sprintf( '<strong>%s</strong>', __( 'Modified Plugins:', 'wp-simple-firewall' ) );
			foreach ( $aAllPlugins as $sPlugins ) {
				$aContent[] = ' - '.$sPlugins;
			}
		}

		if ( !empty( $aAllThemes ) ) {
			$aContent[] = '';
			$aContent[] = sprintf( '<strong>%s</strong>', __( 'Modified Themes:', 'wp-simple-firewall' ) );
			foreach ( $aAllThemes as $sTheme ) {
				$aContent[] = ' - '.$sTheme;
			}
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Plugins/Themes Have Been Altered', 'wp-simple-firewall' ) );
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $sTo, $sEmailSubject, $aContent );

		$this->getCon()->fireEvent(
			'ptg_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
		);
	}
}
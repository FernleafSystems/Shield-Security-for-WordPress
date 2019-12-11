<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services;

class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_HackProtect_ScanAssetsBase {

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
	 * @return Shield\Scans\Ptg\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Shield\Scans\Ptg\Utilities\ItemActionHandler();
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

	public function runHourlyCron() {
		( new HackGuard\Lib\Snapshots\StoreAction\TouchAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function runDailyCron() {
		( new HackGuard\Lib\Snapshots\StoreAction\CleanAll() )
			->setMod( $this->getMod() )
			->run();
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
	 * @param string $sBaseName
	 * @return bool
	 */
	public function reinstall( $sBaseName ) {
		$bSuccess = parent::reinstall( $sBaseName );
		if ( $bSuccess ) {
			try {
				( new HackGuard\Lib\Snapshots\StoreAction\Build() )
					->setMod( $this->getMod() )
					->setAsset( $this->getAssetFromSlug( $sBaseName ) )
					->run();
			}
			catch ( Exception $oE ) {
			}
		}
		return $bSuccess;
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

	/**
	 * @param string $sBaseName
	 * @deprecated 8.5
	 */
	public function onActivatePlugin( $sBaseName ) {
	}

	/**
	 * @deprecated 8.5
	 */
	public function onActivateTheme() {
	}

	/**
	 * @param string $sBaseName
	 * @deprecated 8.5
	 */
	public function onDeactivatePlugin( $sBaseName ) {
	}

	/**
	 * Only snaps active.
	 *
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 * @deprecated 8.5
	 */
	public function updateItemInSnapshot( $sSlug, $sContext = null ) {
		return $this;
	}

	/**
	 * @param string $sSlug
	 * @deprecated 8.5
	 */
	public function updateThemeSnapshot( $sSlug ) {
	}

	/**
	 * @return bool
	 * @deprecated 8.5
	 */
	private function snapshotThemes() {
		return true;
	}

	/**
	 * Will also remove a plugin if it's found to be in-active
	 * Careful: Cannot use this for the activate and deactivate hooks as the WP option
	 * wont be updated
	 *
	 * @param string $sBaseName
	 * @deprecated 8.5
	 */
	public function updatePluginSnapshot( $sBaseName ) {
	}

	/**
	 * @param string $sSlug
	 * @return $this
	 * @deprecated 8.5
	 */
	protected function removeItemSnapshot( $sSlug ) {
		return $this;
	}

	/**
	 * @param WP_Upgrader $oUpgrader
	 * @param array       $aInfo Upgrade/Install Information
	 * @deprecated 8.5
	 */
	public function updateSnapshotAfterUpgrade( $oUpgrader, $aInfo ) {
	}

	/**
	 * @return $this
	 * @deprecated 8.5
	 */
	private function snapshotPlugins() {
		return $this;
	}

	/**
	 * @param string $sBaseFile
	 * @return array
	 * @deprecated 8.5
	 */
	private function buildSnapshotPlugin( $sBaseFile ) {
		return [];
	}

	/**
	 * @param string $sSlug
	 * @return array
	 * @deprecated 8.5
	 */
	private function buildSnapshotTheme( $sSlug ) {
		return [];
	}

	/**
	 * @return null
	 * @deprecated 8.5
	 */
	private function getStore_Plugins() {
		return null;
	}

	/**
	 * @return null
	 * @deprecated 8.5
	 */
	private function getStore_Themes() {
		return null;
	}
}
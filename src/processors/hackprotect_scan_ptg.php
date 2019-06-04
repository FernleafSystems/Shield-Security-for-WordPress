<?php

use FernleafSystems\Wordpress\Plugin\Shield,
	FernleafSystems\Wordpress\Services;

class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_HackProtect_ScanAssetsBase {

	const SCAN_SLUG = 'ptg';

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oSnapshotPlugins;

	/**
	 * @var Shield\Scans\Ptg\Snapshots\Store
	 */
	private $oSnapshotThemes;

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$this->initSnapshots();

		$bStoresExists = $this->getStore_Plugins()->getSnapStoreExists()
						 && $this->getStore_Themes()->getSnapStoreExists();

		// If a build is indicated as required and the store exists, mark them as built.
		if ( $oFO->isPtgBuildRequired() && $bStoresExists ) {
			$oFO->setPtgLastBuildAt();
		}
		else if ( !$bStoresExists ) {
			$oFO->setPtgLastBuildAt( 0 );
		}

		if ( $oFO->isPtgReadyToScan() ) {
			add_action( 'upgrader_process_complete', [ $this, 'updateSnapshotAfterUpgrade' ], 10, 2 );
			add_action( 'activated_plugin', [ $this, 'onActivatePlugin' ], 10 );
			add_action( 'deactivated_plugin', [ $this, 'onDeactivatePlugin' ], 10 );
			add_action( 'switch_theme', [ $this, 'onActivateTheme' ], 10, 0 );
		}

		if ( $oFO->isPtgReinstallLinks() ) {
			add_filter( 'plugin_action_links', [ $this, 'addActionLinkRefresh' ], 50, 2 );
			add_action( 'admin_footer', [ $this, 'printPluginReinstallDialogs' ] );
		}
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->isPtgEnabled();
	}

	/**
	 * @return Shield\Scans\Ptg\ResultsSet
	 */
	protected function getScannerResults() {
		$oResults = $this->scanPlugins();
		( new Shield\Scans\Helpers\CopyResultsSets() )->copyTo( $this->scanThemes(), $oResults );
		return $oResults;
	}

	/**
	 * @param Shield\Scans\Ptg\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\Ptg\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Ptg\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\Ptg\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Ptg\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\Ptg\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\Wcf\Repair|mixed
	 */
	protected function getRepairer() {
		return new Shield\Scans\Ptg\Repair();
	}

	/**
	 * Shouldn't really be used in this case as it'll only scan the plugins
	 *
	 * @return Shield\Scans\Ptg\ScannerPlugins
	 */
	protected function getScanner() {
		return $this->getContextScanner();
	}

	/**
	 * @param string $sContext
	 * @return Shield\Scans\Ptg\ScannerPlugins|Shield\Scans\Ptg\ScannerThemes
	 */
	protected function getContextScanner( $sContext = self::CONTEXT_PLUGINS ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oScanner = ( $sContext == self::CONTEXT_PLUGINS ) ?
			new Shield\Scans\Ptg\ScannerPlugins()
			: new Shield\Scans\Ptg\ScannerThemes();

		return $oScanner->setDepth( $oFO->getPtgDepth() )
						->setFileExts( $oFO->getPtgFileExtensions() );
	}

	/**
	 * @param array  $aLinks
	 * @param string $sPluginFile
	 * @return string[]
	 */
	public function addActionLinkRefresh( $aLinks, $sPluginFile ) {
		$oWpP = Services\Services::WpPlugins();

		if ( $oWpP->isWpOrg( $sPluginFile ) && !$oWpP->isUpdateAvailable( $sPluginFile ) ) {
			$sLinkTemplate = '<a href="javascript:void(0)">%s</a>';
			$aLinks[ 'icwp-reinstall' ] = sprintf(
				$sLinkTemplate,
				'Re-Install'
			);
		}

		return $aLinks;
	}

	/**
	 * @param string $sItem
	 * @return array|null
	 */
	public function getSnapshotItemMeta( $sItem ) {
		$aItem = null;
		if ( $this->getStore_Plugins()->itemExists( $sItem ) ) {
			$aItem = $this->getStore_Plugins()->getSnapItem( $sItem );
		}
		else if ( $this->getStore_Themes()->itemExists( $sItem ) ) {
			$aItem = $this->getStore_Themes()->getSnapItem( $sItem );
		}
		$aMeta = is_array( $aItem ) && !empty( $aItem[ 'meta' ] ) ? $aItem[ 'meta' ] : null;
		return $aMeta;
	}

	/**
	 * @param Shield\Scans\Ptg\ResultItem $oItem
	 * @return true
	 * @throws \Exception
	 */
	protected function assetAccept( $oItem ) {
		// we run it for both since it doesn't matter which context it's in, it'll be removed
		$this->updatePluginSnapshot( $oItem->slug );
		$this->updateThemeSnapshot( $oItem->slug );
		return true;
	}

	public function printPluginReinstallDialogs() {
		$aRenderData = [
			'strings'     => [
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
			],
			'js_snippets' => []
		];
		echo $this->getMod()
				  ->renderTemplate( 'snippets/hg-plugins-reinstall-dialogs.php', $aRenderData );
	}

	/**
	 * @param string $sBaseName
	 */
	public function onActivatePlugin( $sBaseName ) {
		$this->updatePluginSnapshot( $sBaseName );
	}

	/**
	 * When activating a theme we completely rebuild the themes snapshot.
	 */
	public function onActivateTheme() {
		$this->snapshotThemes();
	}

	/**
	 * @param string $sBaseName
	 */
	public function onDeactivatePlugin( $sBaseName ) {
		$this->deletePluginFromSnapshot( $sBaseName );
	}

	/**
	 * @param string $sBaseName
	 * @return bool
	 */
	public function reinstall( $sBaseName ) {
		return parent::reinstall( $sBaseName ) && $this->updateItemInSnapshot( $sBaseName );
	}

	/**
	 * @param WP_Upgrader $oUpgrader
	 * @param array       $aInfo Upgrade/Install Information
	 */
	public function updateSnapshotAfterUpgrade( $oUpgrader, $aInfo ) {

		$sContext = '';
		$aSlugs = [];

		// Need to account for single and bulk updates. First bulk
		if ( !empty( $aInfo[ self::CONTEXT_PLUGINS ] ) ) {
			$sContext = self::CONTEXT_PLUGINS;
			$aSlugs = $aInfo[ $sContext ];
		}
		else if ( !empty( $aInfo[ self::CONTEXT_THEMES ] ) ) {
			$sContext = self::CONTEXT_THEMES;
			$aSlugs = $aInfo[ $sContext ];
		}
		else if ( !empty( $aInfo[ 'plugin' ] ) ) {
			$sContext = self::CONTEXT_PLUGINS;
			$aSlugs = [ $aInfo[ 'plugin' ] ];
		}
		else if ( !empty( $aInfo[ 'theme' ] ) ) {
			$sContext = self::CONTEXT_THEMES;
			$aSlugs = [ $aInfo[ 'theme' ] ];
		}
		else if ( isset( $aInfo[ 'action' ] ) && $aInfo[ 'action' ] == 'install' && isset( $aInfo[ 'type' ] )
				  && !empty( $oUpgrader->result[ 'destination_name' ] ) ) {

			if ( $aInfo[ 'type' ] == 'plugin' ) {
				$oWpPlugins = Services\Services::WpPlugins();
				$sPluginFile = $oWpPlugins->findPluginFileFromDirName( $oUpgrader->result[ 'destination_name' ] );
				if ( !empty( $sPluginFile ) && $oWpPlugins->isActive( $sPluginFile ) ) {
					$sContext = self::CONTEXT_PLUGINS;
					$aSlugs = [ $sPluginFile ];
				}
			}
			else if ( $aInfo[ 'type' ] == 'theme' ) {
				$sDir = $oUpgrader->result[ 'destination_name' ];
				if ( Services\Services::WpThemes()->isActive( $sDir ) ) {
					$sContext = self::CONTEXT_THEMES;
					$aSlugs = [ $sDir ];
				}
			}
		}

		// update snapshots
		if ( is_array( $aSlugs ) ) {
			foreach ( $aSlugs as $sSlug ) {
				$this->updateItemInSnapshot( $sSlug, $sContext );
			}
		}
	}

	/**
	 * @param string $sBaseName - the basename for plugin
	 * @return $this
	 */
	private function deletePluginFromSnapshot( $sBaseName ) {

		$oStore = $this->getStore_Plugins();
		if ( $oStore->itemExists( $sBaseName ) ) {
			try {
				$oStore->removeItemSnapshot( $sBaseName )
					   ->save();
			}
			catch ( \Exception $oE ) {
			}
		}

		return $this;
	}

	/**
	 * Will also remove a plugin if it's found to be in-active
	 * Careful: Cannot use this for the activate and deactivate hooks as the WP option
	 * wont be updated
	 *
	 * @param string $sBaseName
	 */
	public function updatePluginSnapshot( $sBaseName ) {
		$oStore = $this->getStore_Plugins();

		if ( Services\Services::WpPlugins()->isActive( $sBaseName ) ) {
			try {
				$oStore->addSnapItem( $sBaseName, $this->buildSnapshotPlugin( $sBaseName ) )
					   ->save();
			}
			catch ( \Exception $oE ) {
			}
		}
		else if ( $oStore->itemExists( $sBaseName ) ) {
			try {
				$oStore->removeItemSnapshot( $sBaseName )
					   ->save();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param string $sSlug
	 */
	public function updateThemeSnapshot( $sSlug ) {
		$oStore = $this->getStore_Themes();

		if ( Services\Services::WpThemes()->isActive( $sSlug, true ) ) {
			try {
				$oStore->addSnapItem( $sSlug, $this->buildSnapshotTheme( $sSlug ) )
					   ->save();
			}
			catch ( \Exception $oE ) {
			}
		}
		else if ( $oStore->itemExists( $sSlug ) ) {
			try {
				$oStore->removeItemSnapshot( $sSlug )
					   ->save();
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * Only snaps active.
	 *
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 */
	public function updateItemInSnapshot( $sSlug, $sContext = null ) {
		if ( empty( $sContext ) ) {
			$sContext = $this->getContextFromSlug( $sSlug );
		}

		if ( $sContext == self::CONTEXT_THEMES ) {
			$this->updateThemeSnapshot( $sSlug );
		}
		else if ( $sContext == self::CONTEXT_PLUGINS ) {
			$this->updatePluginSnapshot( $sSlug );
		}

		return $this;
	}

	/**
	 * When initiating snapshots, we must clean old results before creating a clean snapshot
	 */
	private function initSnapshots() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$bPluginsRebuildReqd = $oFO->isPtgBuildRequired() || !$this->getStore_Plugins()->getSnapStoreExists();
		$bThemesRebuildReqd = $oFO->isPtgBuildRequired() || !$this->getStore_Themes()->getSnapStoreExists();

		if ( $bPluginsRebuildReqd || $bThemesRebuildReqd ) {
			// grab all the existing results
			$oDbH = $this->getScannerDb()->getDbHandler();
			/** @var Shield\Databases\Scanner\Select $oSel */
			$oSel = $oDbH->getQuerySelector();
			/** @var Shield\Databases\Scanner\EntryVO[] $aRes */
			$aRes = $oSel->filterByScan( static::SCAN_SLUG )->all();

			$oCleaner = ( new Shield\Scans\Ptg\ScanResults\Clean() )
				->setDbHandler( $oDbH )
				->setScannerProfile( $this->getScannerProfile() )
				->setWorkingResultsSet( $this->convertVosToResults( $aRes ) );

			if ( $bPluginsRebuildReqd ) {
				$oCleaner->forPlugins();
				$this->snapshotPlugins();
			}
			if ( $bThemesRebuildReqd ) {
				$oCleaner->forThemes();
				$this->snapshotThemes();
			}
		}

		if ( $oFO->isPtgRebuildSelfRequired() ) {
			// rebuilt self when the plugin itself upgrades
			$this->updatePluginSnapshot( $this->getCon()->getPluginBaseFile() );
			$oFO->setPtgRebuildSelfRequired( false );
		}

		if ( $oFO->isPtgUpdateStoreFormat() ) {
			( new Shield\Scans\Ptg\Snapshots\StoreFormatUpgrade() )
				->setStore( $this->getStore_Plugins() )->run()
				->setStore( $this->getStore_Themes() )->run();
			$oFO->setPtgUpdateStoreFormat( false );
		}
	}

	/**
	 * @param string $sBaseFile
	 * @return array
	 */
	private function buildSnapshotPlugin( $sBaseFile ) {
		$aPlugin = Services\Services::WpPlugins()->getPlugin( $sBaseFile );

		return [
			'meta'   => [
				'name'         => $aPlugin[ 'Name' ],
				'version'      => $aPlugin[ 'Version' ],
				'ts'           => Services\Services::Request()->ts(),
				'snap_version' => $this->getCon()->getVersion(),
			],
			'hashes' => $this->getContextScanner( self::CONTEXT_PLUGINS )->hashAssetFiles( $sBaseFile )
		];
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	private function buildSnapshotTheme( $sSlug ) {
		$oTheme = Services\Services::WpThemes()->getTheme( $sSlug );

		return [
			'meta'   => [
				'name'         => $oTheme->get( 'Name' ),
				'version'      => $oTheme->get( 'Version' ),
				'ts'           => Services\Services::Request()->ts(),
				'snap_version' => $this->getCon()->getVersion(),
			],
			'hashes' => $this->getContextScanner( self::CONTEXT_THEMES )->hashAssetFiles( $sSlug )
		];
	}

	/**
	 * @return $this
	 */
	private function snapshotPlugins() {
		try {
			$oStore = $this->getStore_Plugins()
						   ->deleteSnapshots();
			foreach ( Services\Services::WpPlugins()->getActivePlugins() as $sBaseName ) {
				$oStore->addSnapItem( $sBaseName, $this->buildSnapshotPlugin( $sBaseName ) );
			}
			$oStore->save();
		}
		catch ( \Exception $oE ) {
		}
		return $this;
	}

	/**
	 * @return bool
	 */
	private function snapshotThemes() {
		$bSuccess = true;

		$oWpThemes = Services\Services::WpThemes();
		try {
			$oSnap = $this->getStore_Themes()
						  ->deleteSnapshots();

			$oActiveTheme = $oWpThemes->getCurrent();
			$aThemes = [
				$oActiveTheme->get_stylesheet() => $oActiveTheme
			];

			if ( $oWpThemes->isActiveThemeAChild() ) { // is child theme
				$oParent = $oWpThemes->getCurrentParent();
				$aThemes[ $oActiveTheme->get_template() ] = $oParent;
			}

			/** @var $oTheme WP_Theme */
			foreach ( $aThemes as $sSlug => $oTheme ) {
				$oSnap->addSnapItem( $sSlug, $this->buildSnapshotTheme( $sSlug ) );
			}
			$oSnap->save();
		}
		catch ( \Exception $oE ) {
			$bSuccess = false;
		}

		return $bSuccess;
	}

	/**
	 * @param $sContext
	 * @return Shield\Scans\Ptg\Snapshots\Store
	 */
	private function getStore( $sContext ) {
		return ( $sContext == self::CONTEXT_PLUGINS ) ? $this->getStore_Plugins() : $this->getStore_Themes();
	}

	/**
	 * @return Shield\Scans\Ptg\Snapshots\Store
	 */
	private function getStore_Plugins() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( !isset( $this->oSnapshotPlugins ) ) {
			try {
				$this->oSnapshotPlugins = ( new Shield\Scans\Ptg\Snapshots\Store() )
					->setStorePath( $oFO->getPtgSnapsBaseDir() )
					->setContext( self::CONTEXT_PLUGINS );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oSnapshotPlugins;
	}

	/**
	 * @return Shield\Scans\Ptg\Snapshots\Store
	 */
	private function getStore_Themes() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( !isset( $this->oSnapshotThemes ) ) {
			try {
				$this->oSnapshotThemes = ( new Shield\Scans\Ptg\Snapshots\Store() )
					->setStorePath( $oFO->getPtgSnapsBaseDir() )
					->setContext( self::CONTEXT_THEMES );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oSnapshotThemes;
	}

	/**
	 * @param Shield\Scans\Ptg\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		// no autorepair
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
				'to'  => $sTo,
				'via' => 'email',
			]
		);
	}

	/**
	 * @return Shield\Scans\Ptg\ResultsSet
	 */
	public function scanPlugins() {
		return $this->runSnapshotScan( self::CONTEXT_PLUGINS );
	}

	/**
	 * @return Shield\Scans\Ptg\ResultsSet
	 */
	public function scanThemes() {
		return $this->runSnapshotScan( self::CONTEXT_THEMES );
	}

	/**
	 * @param string $sContext
	 * @return Shield\Scans\Ptg\ResultsSet
	 */
	private function runSnapshotScan( $sContext = self::CONTEXT_PLUGINS ) {
		$aSnapHashes = $this->getStore( $sContext )->getSnapDataHashesOnly();
		return $this->getContextScanner( $sContext )->run( $aSnapHashes );
	}

	/**
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param array  $aData
	 * @return $this
	 */
	public function addToAuditEntry( $sMsg = '', $nCategory = 1, $sEvent = '', $aData = [] ) {
		$sMsg = sprintf( '[%s]: %s', __( 'Plugin/Theme Guard', 'wp-simple-firewall' ), $sMsg );
		$this->createNewAudit( 'wpsf', $sMsg, $nCategory, $sEvent, $aData );
		return $this;
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function deactivatePlugin() {
		try {
			// clear the snapshots
			$this->getStore_Themes()->deleteSnapshots();
			$this->getStore_Plugins()->deleteSnapshots();

			// clear the results
			( new Shield\Scans\Ptg\ScanResults\Clean() )
				->setDbHandler( $this->getScannerDb()->getDbHandler() )
				->setScannerProfile( $this->getScannerProfile() )
				->deleteAllForScan();
		}
		catch ( \Exception $oE ) {
		}
	}
}
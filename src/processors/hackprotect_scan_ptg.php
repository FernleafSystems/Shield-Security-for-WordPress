<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Ptg' ) ) {
	return;
}

require_once( __DIR__.'/hackprotect_scan_base.php' );

use FernleafSystems\Wordpress\Plugin\Shield,
	FernleafSystems\Wordpress\Services;

class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ptg';
	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 * @var Shield\Scans\PTGuard\Snapshots\Store
	 */
	private $oSnapshotPlugins;

	/**
	 * @var Shield\Scans\PTGuard\Snapshots\Store
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
			add_action( 'upgrader_process_complete', array( $this, 'updateSnapshotAfterUpgrade' ), 10, 2 );
			add_action( 'activated_plugin', array( $this, 'onActivatePlugin' ), 10 );
			add_action( 'deactivated_plugin', array( $this, 'onDeactivatePlugin' ), 10 );
			add_action( 'switch_theme', array( $this, 'onActivateTheme' ), 10, 0 );
		}

		if ( $oFO->isPtgReinstallLinks() ) {
			add_filter( 'plugin_action_links', array( $this, 'addActionLinkRefresh' ), 50, 2 );
			add_action( 'admin_footer', array( $this, 'printPluginReinstallDialogs' ) );
		}
	}

	/**
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	protected function getScannerResults() {
		$oResults = $this->scanPlugins();
		( new Shield\Scans\Helpers\CopyResultsSets() )->copyTo( $this->scanThemes(), $oResults );
		return $oResults;
	}

	/**
	 * @param Shield\Scans\PTGuard\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\PTGuard\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\PTGuard\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\PTGuard\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\PTGuard\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\WpCore\Repair|mixed
	 */
	protected function getRepairer() {
//		return new Scans\WpCore\Repair();
	}

	/**
	 * Shouldn't really be used in this case as it'll only scan the plugins
	 *
	 * @return Shield\Scans\PTGuard\ScannerPlugins
	 */
	protected function getScanner() {
		return $this->getContextScanner();
	}

	/**
	 * @param string $sContext
	 * @return Shield\Scans\PTGuard\ScannerPlugins|Shield\Scans\PTGuard\ScannerThemes
	 */
	protected function getContextScanner( $sContext = self::CONTEXT_PLUGINS ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oScanner = ( $sContext == self::CONTEXT_PLUGINS ) ?
			new Shield\Scans\PTGuard\ScannerPlugins()
			: new Shield\Scans\PTGuard\ScannerThemes();

		return $oScanner->setDepth( $oFO->getPtgDepth() )
						->setFileExts( $oFO->getPtgFileExtensions() );
	}

	/**
	 * @param array  $aLinks
	 * @param string $sPluginFile
	 * @return string[]
	 */
	public function addActionLinkRefresh( $aLinks, $sPluginFile ) {
		$oWpP = $this->loadWpPlugins();

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
	 * @param string $sItemId - plugin/theme slug
	 * @return true
	 * @throws Exception
	 */
	protected function ignoreItem( $sItemId ) {
		// we run it for both since it doesn't matter which context it's in, it'll be removed
		$this->updatePluginSnapshot( $sItemId );
		$this->updateThemeSnapshot( $sItemId );
		return true;
	}

	/**
	 * @param string $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		$sContext = $this->getContextFromSlug( $sItemId );
		if ( empty( $sContext ) ) {
			throw new Exception( 'Could not find the item to reinstall.' );
		}

		if ( !$this->getServiceFromContext( $sContext )->isActive( $sItemId ) ) {
			$this->updateItemInSnapshot( $sItemId, $sContext );
			throw new Exception( 'The item is not currently active. Removing from scan...' );
		}

		if ( !$this->reinstall( $sItemId, $sContext ) ) {
			throw new Exception( 'The re-install process has reported as failed.' );
		}
		return true;
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deleteItem( $sItemId ) {
		$sContext = $this->getContextFromSlug( $sItemId );
		if ( $sContext !== self::CONTEXT_PLUGINS ) {
			throw new Exception( 'Could not find the item for processing.' );
		}
		$oService = $this->getServiceFromContext( $sContext );
		if ( !$oService->isActive( $sItemId ) ) {
			throw new Exception( 'Could not find the item for processing.' );
		}

		$oService->deactivate( $sItemId );
		return true;
	}

	/**
	 * @param string $sSlug
	 * @return null|string
	 */
	private function getContextFromSlug( $sSlug ) {
		$sContext = null;
		if ( Services\Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}
		else if ( Services\Services::WpThemes()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_THEMES;
		}
		return $sContext;
	}

	/**
	 * @param string $sContext
	 * @return Services\Core\Plugins|Services\Core\Themes
	 */
	private function getServiceFromContext( $sContext ) {
		return ( $sContext == self::CONTEXT_THEMES ) ? Services\Services::WpThemes() : Services\Services::WpPlugins();
	}

	public function printPluginReinstallDialogs() {
		$aRenderData = array(
			'strings'     => array(
				'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
			),
			'js_snippets' => array()
		);
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
	 * @param string $sContext
	 * @return bool
	 */
	public function reinstall( $sBaseName, $sContext = self::CONTEXT_PLUGINS ) {

		if ( $sContext == self::CONTEXT_PLUGINS ) {
			$oExecutor = $this->loadWpPlugins();
		}
		else {
			$oExecutor = $this->loadWpThemes();
		}

		return $oExecutor->reinstall( $sBaseName, false )
			   && $this->updateItemInSnapshot( $sBaseName, $sContext );
	}

	/**
	 * @param WP_Upgrader $oUpgrader
	 * @param array       $aInfo Upgrade/Install Information
	 */
	public function updateSnapshotAfterUpgrade( $oUpgrader, $aInfo ) {

		$sContext = '';
		$aSlugs = array();

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
			$aSlugs = array( $aInfo[ 'plugin' ] );
		}
		else if ( !empty( $aInfo[ 'theme' ] ) ) {
			$sContext = self::CONTEXT_THEMES;
			$aSlugs = array( $aInfo[ 'theme' ] );
		}
		else if ( isset( $aInfo[ 'action' ] ) && $aInfo[ 'action' ] == 'install' && isset( $aInfo[ 'type' ] )
				  && !empty( $oUpgrader->result[ 'destination_name' ] ) ) {

			if ( $aInfo[ 'type' ] == 'plugin' ) {
				$oWpPlugins = $this->loadWpPlugins();
				$sDir = $oWpPlugins->getFileFromDirName( $oUpgrader->result[ 'destination_name' ] );
				if ( $sDir && $oWpPlugins->isActive( $sDir ) ) {
					$sContext = self::CONTEXT_PLUGINS;
					$aSlugs = array( $sDir );
				}
			}
			else if ( $aInfo[ 'type' ] == 'theme' ) {
				$sDir = $oUpgrader->result[ 'destination_name' ];
				if ( $this->loadWpThemes()->isActive( $sDir ) ) {
					$sContext = self::CONTEXT_THEMES;
					$aSlugs = array( $sDir );
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
				$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures removed for plugin "%s"' ), $sBaseName ) );
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

		if ( $this->loadWpPlugins()->isActive( $sBaseName ) ) {
			try {
				$oStore->addSnapItem( $sBaseName, $this->buildSnapshotPlugin( $sBaseName ) )
					   ->save();
				$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures updated for plugin "%s"' ), $sBaseName ) );
			}
			catch ( \Exception $oE ) {
			}
		}
		else {
			try {
				$oStore->removeItemSnapshot( $sBaseName )
					   ->save();
				$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures updated for theme "%s"' ), $sBaseName ) );
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

		if ( $this->loadWpThemes()->isActive( $sSlug, true ) ) {
			try {
				$oStore->addSnapItem( $sSlug, $this->buildSnapshotTheme( $sSlug ) )
					   ->save();
				$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures updated for theme "%s"' ), $sSlug ) );
			}
			catch ( \Exception $oE ) {
			}
		}
		else {
			try {
				$oStore->removeItemSnapshot( $sSlug )
					   ->save();
				$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures updated for theme "%s"' ), $sSlug ) );
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
	public function updateItemInSnapshot( $sSlug, $sContext = self::CONTEXT_PLUGINS ) {

		$aNewSnapData = null;
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

			$oCleaner = ( new Shield\Scans\PTGuard\ScanResults\Clean() )
				->setDbHandler( $oDbH )
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
	}

	/**
	 * @param string $sBaseFile
	 * @return array
	 */
	private function buildSnapshotPlugin( $sBaseFile ) {
		$aPlugin = $this->loadWpPlugins()
						->getPlugin( $sBaseFile );

		return array(
			'meta'   => array(
				'name'    => $aPlugin[ 'Name' ],
				'version' => $aPlugin[ 'Version' ],
				'ts'      => $this->loadRequest()->ts(),
			),
			'hashes' => $this->getContextScanner( self::CONTEXT_PLUGINS )->hashAssetFiles( $sBaseFile )
		);
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	private function buildSnapshotTheme( $sSlug ) {
		$oTheme = $this->loadWpThemes()
					   ->getTheme( $sSlug );

		return array(
			'meta'   => array(
				'name'    => $oTheme->get( 'Name' ),
				'version' => $oTheme->get( 'Version' ),
				'ts'      => $this->loadRequest()->ts(),
			),
			'hashes' => $this->getContextScanner( self::CONTEXT_THEMES )->hashAssetFiles( $sSlug )
		);
	}

	/**
	 * @return $this
	 */
	private function snapshotPlugins() {
		try {
			$oStore = $this->getStore_Plugins()
						   ->deleteSnapshots();
			foreach ( $this->loadWpPlugins()->getActivePlugins() as $sBaseName ) {
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

		$oWpThemes = $this->loadWpThemes();
		try {
			$oSnap = $this->getStore_Themes()
						  ->deleteSnapshots();

			$oActiveTheme = $oWpThemes->getCurrent();
			$aThemes = array(
				$oActiveTheme->get_stylesheet() => $oActiveTheme
			);

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
	 * @return Shield\Scans\PTGuard\Snapshots\Store
	 */
	private function getStore( $sContext ) {
		return ( $sContext == self::CONTEXT_PLUGINS ) ? $this->getStore_Plugins() : $this->getStore_Themes();
	}

	/**
	 * @return Shield\Scans\PTGuard\Snapshots\Store
	 */
	private function getStore_Plugins() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( !isset( $this->oSnapshotPlugins ) ) {
			try {
				$this->oSnapshotPlugins = ( new Shield\Scans\PTGuard\Snapshots\Store() )
					->setStorePath( $oFO->getPtgSnapsBaseDir() )
					->setContext( self::CONTEXT_PLUGINS );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oSnapshotPlugins;
	}

	/**
	 * @return Shield\Scans\PTGuard\Snapshots\Store
	 */
	private function getStore_Themes() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( !isset( $this->oSnapshotThemes ) ) {
			try {
				$this->oSnapshotThemes = ( new Shield\Scans\PTGuard\Snapshots\Store() )
					->setStorePath( $oFO->getPtgSnapsBaseDir() )
					->setContext( self::CONTEXT_THEMES );
			}
			catch ( \Exception $oE ) {
			}
		}
		return $this->oSnapshotThemes;
	}

	/**
	 * @param Shield\Scans\PTGuard\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		// no autorepair
	}

	/**
	 * @param Shield\Scans\PTGuard\ResultsSet $oRes
	 * @return bool
	 */
	protected function runCronUserNotify( $oRes ) {
		$this->emailResults( $oRes );
		return true;
	}

	/**
	 * @param Shield\Scans\PTGuard\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oWpPlugins = Services\Services::WpPlugins();
		$oWpThemes = Services\Services::WpThemes();

		$aAllPlugins = array();
		foreach ( $oRes->getResultsForPluginsContext()->getUniqueSlugs() as $sBaseFile ) {
			$oP = $oWpPlugins->getPluginAsVo( $sBaseFile );
			if ( !empty( $oP ) ) {
				$sVersion = empty( $oP->Version ) ? '' : ': v'.ltrim( $oP->Version, 'v' );
				$aAllPlugins[] = sprintf( '%s%s', $oP->Name, $sVersion );
			}
		}

		$aAllThemes = array();
		foreach ( $oRes->getResultsForThemesContext()->getUniqueSlugs() as $sBaseFile ) {
			$oTheme = $oWpThemes->getTheme( $sBaseFile );
			if ( !empty( $oTheme ) ) {
				$sVersion = empty( $oTheme->version ) ? '' : ': v'.ltrim( $oTheme->version, 'v' );
				$aAllThemes[] = sprintf( '%s%s', $oTheme->get( 'Name' ), $sVersion );
			}
		}

		$sName = $this->getController()->getHumanName();
		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aContent = array(
			sprintf( _wpsf__( '%s has detected at least 1 Plugins/Themes have been modified on your site.' ), $sName ),
			'',
			sprintf( '<strong>%s</strong>', _wpsf__( 'You will receive only 1 email notification about these changes in a 1 week period.' ) ),
			'',
			sprintf( '%s: %s', _wpsf__( 'Site URL' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			'',
			_wpsf__( 'Details of the problem items are below:' ),
		);

		if ( !empty( $aAllPlugins ) ) {
			$aContent[] = '';
			$aContent[] = sprintf( '<strong>%s</strong>', _wpsf__( 'Modified Plugins:' ) );
			foreach ( $aAllPlugins as $sPlugins ) {
				$aContent[] = ' - '.$sPlugins;
			}
		}

		if ( !empty( $aAllThemes ) ) {
			$aContent[] = '';
			$aContent[] = sprintf( '<strong>%s</strong>', _wpsf__( 'Modified Themes:' ) );
			foreach ( $aAllThemes as $sTheme ) {
				$aContent[] = ' - '.$sTheme;
			}
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'Plugins/Themes Have Been Altered' ) );
		$bSendSuccess = $this->getEmailProcessor()
							 ->sendEmailWithWrap( $sTo, $sEmailSubject, $aContent );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin/Theme Guard email alert to: %s' ), $sTo ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin/Theme Guard email alert to: %s' ), $sTo ) );
		}
	}

	/**
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	public function scanPlugins() {
		return $this->runSnapshotScan( self::CONTEXT_PLUGINS );
	}

	/**
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	public function scanThemes() {
		return $this->runSnapshotScan( self::CONTEXT_THEMES );
	}

	/**
	 * @param string $sContext
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	private function runSnapshotScan( $sContext = self::CONTEXT_PLUGINS ) {
		$aSnapHashes = $this->getStore( $sContext )->getSnapDataHashesOnly();
		return $this->getContextScanner( $sContext )->run( $aSnapHashes );
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getPtgCronName();
	}

	/**
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param array  $aData
	 * @return $this
	 */
	public function addToAuditEntry( $sMsg = '', $nCategory = 1, $sEvent = '', $aData = array() ) {
		$sMsg = sprintf( '[%s]: %s', _wpsf__( 'Plugin/Theme Guard' ), $sMsg );
		$this->createNewAudit( 'wpsf', $sMsg, $nCategory, $sEvent, $aData );
		return $this;
	}
}
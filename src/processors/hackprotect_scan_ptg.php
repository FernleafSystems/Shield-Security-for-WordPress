<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Ptg' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/hackprotect_scan_base.php' );

use FernleafSystems\Wordpress\Plugin\Shield,
	FernleafSystems\Wordpress\Services;

class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ptg';
	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 */
	public function run() {
		parent::run();

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isPtgReadyToScan() ) {

			if ( !$this->storeExists( self::CONTEXT_PLUGINS ) ) {
				$this->snapshotPlugins();
			}
			if ( !$this->storeExists( self::CONTEXT_THEMES ) ) {
				$this->snapshotThemes();
			}

			add_action( 'upgrader_process_complete', array( $this, 'updateSnapshotAfterUpgrade' ), 10, 2 );
			add_action( 'activated_plugin', array( $this, 'onActivatePlugin' ), 10 );
			add_action( 'deactivated_plugin', array( $this, 'onDeactivatePlugin' ), 10 );
			add_action( 'switch_theme', array( $this, 'onActivateTheme' ), 10, 0 );
		}
		else if ( $oFO->isPtgBuildRequired() ) {
			$this->rebuildSnapshots();
			if ( $this->storeExists( self::CONTEXT_PLUGINS ) && $this->storeExists( self::CONTEXT_THEMES ) ) {
				$oFO->setPtgLastBuildAt();
			}
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
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\PTGuard\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\PTGuard\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
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
	 * @param $sItemId - plugin/theme slug
	 * @return true
	 * @throws Exception
	 */
	protected function ignoreItem( $sItemId ) {
		$sContext = $this->getContextFromSlug( $sItemId );
		if ( empty( $sContext ) ) {
			throw new Exception( 'Could not find the item for processing.' );
		}

		$this->updateItemInSnapshot( $sItemId, $sContext );

		return true;
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		$sContext = $this->getContextFromSlug( $sItemId );
		if ( empty( $sContext ) ) {
			throw new Exception( 'Could not find the item for processing.' );
		}
		$oService = $this->getServiceFromContext( $sContext );
		if ( !$oService->isActive( $sItemId ) ) {
			throw new Exception( 'Could not find the item for processing.' );
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
		if ( Services\Services::WpPlugins()->isActive( $sSlug ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}
		else if ( Services\Services::WpThemes()->isActive( $sSlug ) ) {
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
		$this->updateItemInSnapshot( $sBaseName, self::CONTEXT_PLUGINS );
	}

	/**
	 */
	public function onActivateTheme() {
		$this->deleteStore( self::CONTEXT_THEMES )
			 ->snapshotThemes();
	}

	/**
	 * @param string $sBaseName
	 */
	public function onDeactivatePlugin( $sBaseName ) {
		$this->deleteItemFromSnapshot( $sBaseName, self::CONTEXT_PLUGINS );
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

		// update snaptshots
		if ( is_array( $aSlugs ) ) {
			foreach ( $aSlugs as $sSlug ) {
				$this->updateItemInSnapshot( $sSlug, $sContext );
			}
		}
	}

	/**
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 */
	public function deleteItemFromSnapshot( $sSlug, $sContext = self::CONTEXT_PLUGINS ) {
		$aSnapshot = $this->loadSnapshotData( $sContext );
		if ( isset( $aSnapshot[ $sSlug ] ) ) {
			unset( $aSnapshot[ $sSlug ] );
			$this->addToAuditEntry( sprintf( _wpsf__( 'File signatures removed for item "%s"' ), $sSlug ) )
				 ->storeSnapshot( $aSnapshot, $sContext );
		}
		return $this;
	}

	/**
	 * Only snaps active.
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 */
	public function updateItemInSnapshot( $sSlug, $sContext = self::CONTEXT_PLUGINS ) {

		$aNewSnapData = null;
		if ( $sContext == self::CONTEXT_PLUGINS && $this->loadWpPlugins()->isActive( $sSlug ) ) {
			$aNewSnapData = $this->snapshotPlugin( $sSlug );
		}
		if ( $sContext == self::CONTEXT_THEMES && $this->loadWpThemes()->isActive( $sSlug, true ) ) {
			$aNewSnapData = $this->snapshotTheme( $sSlug );
		}

		if ( $aNewSnapData ) {
			$aSnapshot = $this->loadSnapshotData( $sContext );
			$aSnapshot[ $sSlug ] = $aNewSnapData;
			$this->storeSnapshot( $aSnapshot, $sContext )
				 ->addToAuditEntry( sprintf( _wpsf__( 'File signatures updated for item "%s"' ), $sSlug ) );
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function rebuildSnapshots() {
		return $this->deleteStores()
					->setupSnapshots();
	}

	/**
	 * @return $this
	 */
	protected function setupSnapshots() {
		$this->snapshotPlugins();
		$this->snapshotThemes();
		return $this;
	}

	/**
	 * @param string $sBaseFile
	 * @return array
	 */
	private function snapshotPlugin( $sBaseFile ) {
		$aPlugin = $this->loadWpPlugins()
						->getPlugin( $sBaseFile );

		return array(
			'meta'   => array(
				'name'    => $aPlugin[ 'Name' ],
				'version' => $aPlugin[ 'Version' ],
				'ts'      => $this->loadRequest()->ts(),
			),
			'hashes' => $this->hashPluginFiles( $sBaseFile )
		);
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	private function snapshotTheme( $sSlug ) {
		$oTheme = $this->loadWpThemes()
					   ->getTheme( $sSlug );
		return array(
			'meta'   => array(
				'name'    => $oTheme->get( 'Name' ),
				'version' => $oTheme->get( 'Version' ),
				'ts'      => $this->loadRequest()->ts(),
			),
			'hashes' => $this->hashThemeFiles( $sSlug )
		);
	}

	/**
	 * @return $this
	 */
	private function snapshotPlugins() {
		$oWpPl = $this->loadWpPlugins();

		$aSnapshot = array();
		foreach ( $oWpPl->getInstalledBaseFiles() as $sBaseName ) {
			if ( $oWpPl->isActive( $sBaseName ) ) {
				$aSnapshot[ $sBaseName ] = $this->snapshotPlugin( $sBaseName );
			}
		}
		return $this->storeSnapshot( $aSnapshot, self::CONTEXT_PLUGINS );
	}

	/**
	 * @return $this
	 */
	private function snapshotThemes() {
		$oWpThemes = $this->loadWpThemes();

		$oActiveTheme = $oWpThemes->getCurrent();
		$aThemes = array(
			$oActiveTheme->get_stylesheet() => $oActiveTheme
		);

		if ( $oWpThemes->isActiveThemeAChild() ) { // is child theme
			$oParent = $oWpThemes->getCurrentParent();
			$aThemes[ $oActiveTheme->get_template() ] = $oParent;
		}

		$aSnapshot = array();
		/** @var $oTheme WP_Theme */
		foreach ( $aThemes as $sSlug => $oTheme ) {
			$aSnapshot[ $sSlug ] = $this->snapshotTheme( $sSlug );
		}
		return $this->storeSnapshot( $aSnapshot, self::CONTEXT_THEMES );
	}

	/**
	 * @param string $sContext
	 * @return bool
	 */
	protected function storeExists( $sContext = self::CONTEXT_PLUGINS ) {
		return $this->loadFS()
					->isFile( path_join( $this->getSnapsBaseDir(), $sContext.'.txt' ) );
	}

	/**
	 * @return $this
	 */
	public function deleteStores() {
		return $this->deleteStore( self::CONTEXT_PLUGINS )
					->deleteStore( self::CONTEXT_THEMES );
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function deleteStore( $sContext = self::CONTEXT_PLUGINS ) {
		$this->loadFS()
			 ->deleteDir( path_join( $this->getSnapsBaseDir(), $sContext.'.txt' ) );
		return $this;
	}

	/**
	 * @param array  $aSnapshot
	 * @param string $sContext
	 * @return $this
	 */
	private function storeSnapshot( $aSnapshot, $sContext = self::CONTEXT_PLUGINS ) {
		$oWpFs = $this->loadFS();
		$sDir = $this->getSnapsBaseDir();
		$sSnap = path_join( $sDir, $sContext.'.txt' );
		$oWpFs->mkdir( $sDir );
		$oWpFs->putFileContent( $sSnap, base64_encode( json_encode( $aSnapshot ) ) );
		return $this;
	}

	/**
	 * @param string $sContext
	 * @return array
	 */
	private function loadSnapshotData( $sContext = self::CONTEXT_PLUGINS ) {
		$aDecoded = array();

		$sSnap = path_join( $this->getSnapsBaseDir(), $sContext.'.txt' );

		$sRaw = $this->loadFS()
					 ->getFileContent( $sSnap );
		if ( !empty( $sRaw ) ) {
			$aDecoded = json_decode( base64_decode( $sRaw ), true );
		}
		return $aDecoded;
	}

	/**
	 * @param string $sSlugBaseName
	 * @return string[]
	 */
	protected function hashPluginFiles( $sSlugBaseName ) {
		return $this->getContextScanner( self::CONTEXT_PLUGINS )
					->hashAssetFiles( $sSlugBaseName );
	}

	/**
	 * @param string $sSlugStylesheet
	 * @return string[]
	 */
	protected function hashThemeFiles( $sSlugStylesheet ) {
		return $this->getContextScanner( self::CONTEXT_THEMES )
					->hashAssetFiles( $sSlugStylesheet );
	}

	/**
	 * @param string $sSlug
	 * @param string $sContext
	 * @return string[]
	 */
	protected function hashFiles( $sSlug, $sContext = self::CONTEXT_PLUGINS ) {
		switch ( $sContext ) {

			case self::CONTEXT_PLUGINS:
				return $this->hashPluginFiles( $sSlug );
				break;

			case self::CONTEXT_THEMES:
				return $this->hashThemeFiles( $sSlug );
				break;

			default:
				return array();
				break;
		}
	}

	/**
	 * @param Shield\Scans\PTGuard\ResultsSet $oRes
	 */
	protected function handleScanResults( $oRes ) {
		if ( true || $this->canSendResults( $oRes ) ) { // TODO
			$this->emailResults( $oRes );
		}
		else {
			$this->addToAuditEntry( _wpsf__( 'Silenced repeated email alert from Plugin/Theme Scan Guard' ) );
		}
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
	 * @param array $aResults
	 * @return bool
	 */
	private function canSendResults( $aResults ) {
		return ( $this->getResultsHashTime( md5( serialize( $aResults ) ) ) === 0 );
	}

	/**
	 * @param string $sResultHash
	 * @return int
	 */
	private function getResultsHashTime( $sResultHash ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$aTracking = $oFO->getPtgEmailTrackData();

		$nSent = isset( $aTracking[ $sResultHash ] ) ? $aTracking[ $sResultHash ] : 0;

		if ( $this->time() - $nSent > WEEK_IN_SECONDS ) { // reset
			$nSent = 0;
		}

		if ( $nSent == 0 ) { // we've seen this changeset before.
			$aTracking[ $sResultHash ] = $this->time();
			$oFO->setPtgEmailTrackData( $aTracking );
		}

		return $nSent;
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
	 * @param Shield\Scans\PTGuard\ResultsSet $oResults
	 * @return array[]
	 */
	protected function organiseScanDataForDisplay( $oResults ) {

		$aResults = array();
		foreach ( $oResults->getUniqueSlugs() as $sSlug ) {
			$aItemResults = array();

			$oResSlug = $oResults->getResultsSetForSlug( $sSlug );
			if ( $oResSlug->countDifferent() > 0 ) {
				$aItemResults[ 'different' ] = array_map(
					function ( $sPath ) {
						return ltrim( str_replace( WP_CONTENT_DIR, '', $sPath ), '/' );
					},
					$oResSlug->filterItemsForPaths( $oResSlug->getDifferentItems() )
				);
			}

			if ( $oResSlug->countUnrecognised() > 0 ) {
				$aItemResults[ 'unrecognised' ] = array_map(
					function ( $sPath ) {
						return ltrim( str_replace( WP_CONTENT_DIR, '', $sPath ), '/' );
					},
					$oResSlug->filterItemsForPaths( $oResSlug->getUnrecognisedItems() )
				);
			}

			if ( $oResSlug->countDifferent() > 0 ) {
				$aItemResults[ 'missing' ] = array_map(
					function ( $sPath ) {
						return ltrim( str_replace( WP_CONTENT_DIR, '', $sPath ), '/' );
					},
					$oResSlug->filterItemsForPaths( $oResSlug->getMissingItems() )
				);
			}
			$aResults[ $sSlug ] = $aItemResults;
		}
		return $aResults;
	}

	/**
	 * @param string $sContext
	 * @return Shield\Scans\PTGuard\ResultsSet
	 */
	protected function runSnapshotScan( $sContext = self::CONTEXT_PLUGINS ) {
		$aSnaps = array_map(
			function ( $aSnap ) {
				return $aSnap[ 'hashes' ];
			},
			$this->loadSnapshotData( $sContext )
		);
		return $this->getContextScanner( $sContext )->run( $aSnaps );
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
	 * @return string
	 */
	private function getSnapsBaseDir() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getPtgSnapsBaseDir();
	}

	/**
	 * @param string $sMsg
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param string $sWpUsername
	 * @return $this
	 */
	public function addToAuditEntry( $sMsg = '', $nCategory = 1, $sEvent = '', $sWpUsername = '' ) {
		$sMsg = sprintf( '[%s]: %s', _wpsf__( 'Plugin/Theme Guard' ), $sMsg );
		parent::addToAuditEntry( $sMsg, $nCategory, $sEvent, $sWpUsername );
		return $this;
	}
}

class GuardRecursiveFilterIterator extends RecursiveFilterIterator {

	/**
	 * @var bool
	 */
	private $bHasExtensions;

	/**
	 * @var array
	 */
	private $aExtensions;

	/**
	 * @return string[]
	 */
	public function getExtensions() {
		return is_array( $this->aExtensions ) ? $this->aExtensions : array();
	}

	/**
	 * @return bool
	 */
	public function hasExtensions() {
		if ( !isset( $this->bHasExtensions ) ) {
			$aExt = $this->getExtensions();
			$this->bHasExtensions = !empty( $aExt );
		}
		return $this->bHasExtensions;
	}

	/**
	 * @param string[] $aExtensions
	 * @return GuardRecursiveFilterIterator
	 */
	public function setExtensions( $aExtensions ) {
		$this->aExtensions = $aExtensions;
		return $this;
	}

	public function accept() {
		/** @var SplFileInfo $oCurrent */
		$oCurrent = $this->current();

		$bConsumeFile = !in_array( $oCurrent->getFilename(), array( '.', '..' ) );
		if ( $bConsumeFile && $oCurrent->isFile() && $this->hasExtensions() ) {
			$bConsumeFile = in_array( $oCurrent->getExtension(), $this->getExtensions() );
		}

		return $bConsumeFile;
	}
}
<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_PTGuard' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

class ICWP_WPSF_Processor_HackProtect_PTGuard extends ICWP_WPSF_Processor_CronBase {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 */
	public function run() {
		parent::run();

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isPtgReadyToScan() ) {
			add_action( 'upgrader_process_complete', array( $this, 'updateSnapshotAfterUpgrade' ), 10, 2 );
			add_action( 'activated_plugin', array( $this, 'onActivatePlugin' ), 10 );
			add_action( 'deactivated_plugin', array( $this, 'onDeactivatePlugin' ), 10 );
			add_action( 'switch_theme', array( $this, 'onActivateTheme' ), 10, 0 );
		}
		else if ( $oFO->isPtgBuildRequired() ) {
			$this->rebuildSnapshots(); // TODO: Consider if we can't write to disk - we do this forever.
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

	public function printPluginReinstallDialogs() {

		$aRenderData = array(
			'strings'     => array(
				'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
			),
			'js_snippets' => array()
		);
		echo $this->getFeature()
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

		$bSuccess = $oExecutor->reinstall( $sBaseName, false );

		if ( $bSuccess ) {
			$this->updateItemInSnapshot( $sBaseName, $sContext );
		}

		return $bSuccess;
	}

	/**
	 * @param WP_Upgrader $oUpgrader
	 * @param array       $aUpgradeInfo
	 */
	public function updateSnapshotAfterUpgrade( $oUpgrader, $aUpgradeInfo ) {

		$sContext = '';
		if ( !empty( $aUpgradeInfo[ self::CONTEXT_PLUGINS ] ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}
		else if ( !empty( $aUpgradeInfo[ self::CONTEXT_PLUGINS ] ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}

		if ( !empty( $sContext ) ) {
			foreach ( $aUpgradeInfo[ $sContext ] as $sSlug ) {
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
			$this->storeSnapshot( $aSnapshot, $sContext );
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
			$this->storeSnapshot( $aSnapshot, $sContext );
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
				'ts'      => $this->loadDP()->time(),
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
				'ts'      => $this->loadDP()->time(),
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
		foreach ( $oWpPl->getInstalledPluginFiles() as $sBaseName ) {
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
		return $this->hashFilesInDir( $this->loadWpPlugins()->getInstallationDir( $sSlugBaseName ) );
	}

	/**
	 * @param string $sSlugStylesheet
	 * @return string[]
	 */
	protected function hashThemeFiles( $sSlugStylesheet ) {
		$sDir = $this->loadWpThemes()
					 ->getTheme( $sSlugStylesheet )
					 ->get_stylesheet_directory();
		return $this->hashFilesInDir( $sDir );
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
	 * @param string $sDir
	 * @return string[]
	 */
	private function hashFilesInDir( $sDir ) {
		$aSnaps = array();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$nDepth = $oFO->getPtgDepth();
		foreach ( $this->loadFS()->getFilesInDir( $sDir, $nDepth, $this->getIterator( $sDir ) ) as $oFile ) {
			$aSnaps[ $oFile->getRealPath() ] = md5_file( $oFile->getPathname() );
		}
		return $aSnaps;
	}

	/**
	 * @param string $sDir
	 * @return GuardRecursiveFilterIterator
	 */
	private function getIterator( $sDir ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$oIt = new GuardRecursiveFilterIterator( new RecursiveDirectoryIterator( $sDir ) );
		return $oIt->setExtensions( $oFO->getPtgFileExtensions() );
	}

	/**
	 * Cron callback
	 */
	public function cron_runGuardScan() {
		$aPs = $this->scanPlugins();
		$aTs = $this->scanThemes();

		$aResults = array();
		if ( !empty( $aPs ) ) {
			$aResults[ self::CONTEXT_PLUGINS ] = $aPs;
		}
		if ( !empty( $aTs ) ) {
			$aResults[ self::CONTEXT_THEMES ] = $aTs;
		}

		// Only email if there's results
		if ( !empty( $aResults ) ) {

			if ( $this->canSendResults( $aResults ) ) {
				$this->emailResults( $aResults );
			}
			else {
				$this->addToAuditEntry( _wpsf__( 'Silenced repeated email alert from Plugin/Theme Scan Guard' ) );
			}
		}
	}

	/**
	 * @param array[][] $aResults
	 */
	protected function emailResults( $aResults ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		// Plugins
		$aAllPlugins = array();
		if ( isset( $aResults[ self::CONTEXT_PLUGINS ] ) ) {
			$oPlgs = $this->loadWpPlugins();
			$aAllPlugins = array_filter( array_map(
				function ( $sBaseFile ) use ( $oPlgs ) {
					$aData = $oPlgs->getPlugin( $sBaseFile );
					return sprintf( '%s: v%s', $aData[ 'Name' ], ltrim( $aData[ 'Version' ], 'v' ) );
				},
				array_keys( $aResults[ self::CONTEXT_PLUGINS ] )
			) );
		}

		// Themes
		$aAllThemes = array();
		if ( isset( $aResults[ self::CONTEXT_THEMES ] ) ) {
			$oThms = $this->loadWpThemes();
			$aAllThemes = array_filter( array_map(
				function ( $sBaseFile ) use ( $oThms ) {
					$oTheme = $oThms->getTheme( $sBaseFile );
					return sprintf( '%s: v%s', $oTheme->get( 'Name' ), ltrim( $oTheme->get( 'Version' ), 'v' ) );
				},
				array_keys( $aResults[ self::CONTEXT_THEMES ] )
			) );
		}

		$sName = $this->getController()->getHumanName();
		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aContent = array(
			sprintf( _wpsf__( '%s has detected at least 1 Plugins/Themes have been modified on your site.' ), $sName ),
			'',
			sprintf( '<strong>%s</strong>', _wpsf__( 'You will receive only 1 email notification about these changes in a 1 week period.' ) ),
			'',
			sprintf( _wpsf__( 'Site URL - %s' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
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

		if ( $oFO->canRunWizards() ) {
			$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
				$oFO->getUrl_Wizard( 'ptg' ),
				'border:1px solid;padding:20px;line-height:19px;margin:10px 20px;display:inline-block;text-align:center;width:290px;font-size:18px;',
				_wpsf__( 'Run the scanner' )
			);
			$aContent[] = '';
		}

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Plugins/Themes Have Been Altered' ) );
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
		$oFO = $this->getFeature();

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
	 * @return array[][]
	 */
	public function scanPlugins() {
		return $this->runSnapshotScan( self::CONTEXT_PLUGINS );
	}

	/**
	 * @return array[][]
	 */
	public function scanThemes() {
		return $this->runSnapshotScan( self::CONTEXT_THEMES );
	}

	/**
	 * @param string $sContext
	 * @return array[][] - keys are slugs
	 */
	protected function runSnapshotScan( $sContext = self::CONTEXT_PLUGINS ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$bProblemDiscovered = false;
		$aResults = array();
		foreach ( $this->loadSnapshotData( $sContext ) as $sBaseName => $aSnap ) {

			$aItemResults = array();

			// First grab all the current hashes.
			try {
				$aLiveHashes = $this->hashFiles( $sBaseName, $sContext );
			}
			catch ( Exception $oE ) {
				// happens when a plugin/theme no longer exists on disk and we try to get its hashes.
				// an exception is thrown by the recursive directory iterator
//				$this->deleteItemFromSnapshot( $sBaseName, $sContext );

				// We now imagine the whole folder is "missing" and we list all files as missing.
				$aLiveHashes = array();
			}

			// todo: array_diff_assoc ?
			$aDifferent = array();
			foreach ( $aSnap[ 'hashes' ] as $sFile => $sHash ) {
				if ( isset( $aLiveHashes[ $sFile ] ) && $aLiveHashes[ $sFile ] != $sHash ) {
					$aDifferent[] = $sFile;
				}
			}
			if ( !empty( $aDifferent ) ) {
				$aItemResults[ 'different' ] = $aDifferent;
			}

			// 2nd: Identify live files that exist but not in the cache.
			$aUnrecog = array_diff_key( $aLiveHashes, $aSnap[ 'hashes' ] );
			if ( !empty( $aUnrecog ) ) {
				$aItemResults[ 'unrecognised' ] = array_keys( $aUnrecog );
			}

			// 3rd: Identify files in the cache but have disappeared from live
			$aMiss = array_diff_key( $aSnap[ 'hashes' ], $aLiveHashes );
			if ( !empty( $aMiss ) ) {
				$aItemResults[ 'missing' ] = array_keys( $aMiss );
			}

			if ( !empty( $aItemResults ) ) {
				$bProblemDiscovered = true;
				$aItemResults[ 'meta' ] = $aSnap[ 'meta' ];
				$aResults[ $sBaseName ] = $aItemResults;
			}
		}

		$bProblemDiscovered ? $oFO->setLastScanProblemAt( 'ptg' ) : $oFO->clearLastScanProblemAt( 'ptg' );
		$oFO->setLastScanAt( 'ptg' );

		return $aResults;
	}

	/**
	 * @return callable
	 */
	protected function getCronCallback() {
		return array( $this, 'cron_runGuardScan' );
	}

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->getScanFrequency();
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->getPtgCronName();
	}

	/**
	 * @return string
	 */
	private function getSnapsBaseDir() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		return $oFO->getPtgSnapsBaseDir();
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
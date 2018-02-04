<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_GuardLocker' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

class ICWP_WPSF_Processor_HackProtect_GuardLocker extends ICWP_WPSF_Processor_CronBase {

	/**
	 */
	public function run() {
		parent::run();

//		var_dump( $this->scanThemes() );
//		die();
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
			if ( $this->storeExists( 'plugins' ) && $this->storeExists( 'themes' ) ) {
				$oFO->setPtgLastBuildAt();
			}
		}
	}

	/**
	 * @param string $sBaseName
	 */
	public function onActivatePlugin( $sBaseName ) {
		$this->updateItemInSnapshot( $sBaseName, 'plugins' );
	}

	/**
	 */
	public function onActivateTheme() {
		$this->deleteStore( 'themes' );
		$this->snapshotThemes();
	}

	/**
	 * @param string $sBaseName
	 */
	public function onDeactivatePlugin( $sBaseName ) {
		$this->deleteItemFromSnapshot( $sBaseName, 'plugins' );
	}

	/**
	 * @param WP_Upgrader $oUpgrader
	 * @param array       $aUpgradeInfo
	 */
	public function updateSnapshotAfterUpgrade( $oUpgrader, $aUpgradeInfo ) {

		$sContext = '';
		if ( !empty( $aUpgradeInfo[ 'plugins' ] ) ) {
			$sContext = 'plugins';
		}
		else if ( !empty( $aUpgradeInfo[ 'themes' ] ) ) {
			$sContext = 'themes';
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
	public function deleteItemFromSnapshot( $sSlug, $sContext = 'plugins' ) {
		$aSnapshot = $this->loadSnapshotData( $sContext );
		if ( isset( $aSnapshot[ $sSlug ] ) ) {
			unset( $aSnapshot[ $sSlug ] );
			$this->storeSnapshot( $aSnapshot, $sContext );
		}
		return $this;
	}

	/**
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 */
	public function updateItemInSnapshot( $sSlug, $sContext = 'plugins' ) {
		$aSnapshot = $this->loadSnapshotData( $sContext );
		if ( $sContext == 'plugins' ) {
			$aNewData = $this->snapshotPlugin( $sSlug );
		}
		else {
			$aNewData = $this->snapshotTheme( $sSlug );
		}
		$aSnapshot[ $sSlug ] = $aNewData;
		return $this->storeSnapshot( $aSnapshot, $sContext );
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
	 * @param string $sBaseName
	 * @return array
	 */
	private function snapshotPlugin( $sBaseName ) {
		$aPlugin = $this->loadWpPlugins()
						->getPlugin( $sBaseName );

		return array(
			'version' => $aPlugin[ 'Version' ],
			'ts'      => $this->loadDP()->time(),
			'hashes'  => $this->hashPluginFiles( $sBaseName )
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
			'version' => $oTheme->get( 'Version' ),
			'ts'      => $this->loadDP()->time(),
			'hashes'  => $this->hashThemeFiles( $sSlug )
		);
	}

	/**
	 * @return $this
	 */
	private function snapshotPlugins() {
		$oWpPl = $this->loadWpPlugins();

		$aSnapshot = array();
		foreach ( $oWpPl->getInstalledPluginFiles() as $sBaseName ) {
			$aSnapshot[ $sBaseName ] = $this->snapshotPlugin( $sBaseName );
		}
		$this->storeSnapshot( $aSnapshot, 'plugins' );

		return $this;
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
		$this->storeSnapshot( $aSnapshot, 'themes' );

		return $this;
	}

	/**
	 * @param string $sContext
	 * @return bool
	 */
	protected function storeExists( $sContext = 'plugins' ) {
		return $this->loadFS()
					->isFile( path_join( $this->getSnapsBaseDir(), $sContext.'.txt' ) );
	}

	/**
	 * @return $this
	 */
	public function deleteStores() {
		return $this->deleteStore( 'plugins' )
					->deleteStore( 'themes' );
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function deleteStore( $sContext = 'plugins' ) {
		$this->loadFS()
			 ->deleteDir( path_join( $this->getSnapsBaseDir(), $sContext.'.txt' ) );
		return $this;
	}

	/**
	 * @param array  $aSnapshot
	 * @param string $sContext
	 * @return $this
	 */
	private function storeSnapshot( $aSnapshot, $sContext = 'plugins' ) {
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
	private function loadSnapshotData( $sContext = 'plugins' ) {
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
		$sDir = dirname( path_join( WP_PLUGIN_DIR, $sSlugBaseName ) );
		return $this->hashFilesInDir( $sDir );
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
	protected function hashFiles( $sSlug, $sContext = 'plugins' ) {
		switch ( $sContext ) {
			case 'plugins':
				return $this->hashPluginFiles( $sSlug );
				break;

			case 'themes':
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
		return new GuardRecursiveFilterIterator( new RecursiveDirectoryIterator( $sDir ) );
	}

	/**
	 * Cron callback
	 */
	public function cron_runLockerScan() {
		$this->scanPlugins();
		$this->scanThemes();
	}

	/**
	 * @return array[]
	 */
	public function scanPlugins() {
		return $this->runSnapshotScan( 'plugins' );
	}

	/**
	 * @return array[]
	 */
	public function scanThemes() {
		return $this->runSnapshotScan( 'themes' );
	}

	/**
	 * @param string $sContext
	 * @return array[]
	 */
	protected function runSnapshotScan( $sContext = 'plugins' ) {

		$aDifferences = array();
		$aUnrecognised = array();
		$aMissing = array();

		$aSnaps = $this->loadSnapshotData( $sContext );
		foreach ( $aSnaps as $sBaseName => $aSnap ) {

			// First find the difference between live hashes and cached.
			$aLiveHashes = $this->hashFiles( $sBaseName, $sContext );
			$aDifferent = array();
			foreach ( $aSnap[ 'hashes' ] as $sFile => $sHash ) {
				if ( $aLiveHashes[ $sFile ] != $sHash ) {
					$aDifferent[] = $sFile;
				}
			}
			if ( !empty( $aDifferent ) ) {
				$aDifferences[ $sBaseName ] = $aDifferent;
			}

			// 2nd: Identify live files that exist but not in the cache.
			$aUnrecog = array_diff_key( $aLiveHashes, $aSnap[ 'hashes' ] );
			if ( !empty( $aUnrecog ) ) {
				$aUnrecognised[ $sBaseName ] = array_keys( $aUnrecog ); // just filenames
			}

			// 3rd: Identify files in the cache but have disappeared from live
			$aMiss = array_diff_key( $aSnap[ 'hashes' ], $aLiveHashes );
			if ( !empty( $aMiss ) ) {
				$aMissing[ $sBaseName ] = array_keys( $aMiss ); // just filenames
			}
		}

		return array(
			'different'    => $aDifferences,
			'unrecognised' => $aUnrecognised,
			'missing'      => $aMissing,
		);
	}

	/**
	 * @return callable
	 */
	protected function getCronCallback() {
		return array( $this, 'cron_runLockerScan' );
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
		return path_join( WP_CONTENT_DIR, 'shield/locker' );
	}
}

class GuardRecursiveFilterIterator extends RecursiveFilterIterator {

	public function accept() {
		/** @var SplFileInfo $oCurrent */
		$oCurrent = $this->current();

		$bConsumeFile = !in_array( $oCurrent->getFilename(), array( '.', '..' ) );
		if ( $bConsumeFile && $oCurrent->isFile() ) {
			$bConsumeFile = in_array( $oCurrent->getExtension(), array( 'php' ) );
		}

		return $bConsumeFile;
	}
}
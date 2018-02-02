<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Locker' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

class ICWP_WPSF_Processor_HackProtect_Locker extends ICWP_WPSF_Processor_CronBase {

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
	}

	/**
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param string $sContext
	 * @return $this
	 */
	public function deleteItemFromSnapshot( $sSlug, $sContext = 'plugins' ) {
		$aSnapshot = $this->loadSnapshot( $sContext );
		if ( isset( $aSnapshot[ $sSlug ] ) ) {
			unset( $aSnapshot[ $sSlug ] );
			$this->storeSnapshot( $aSnapshot, $sContext );
		}
		return $this;
	}

	/**
	 * @param string $sSlug - the basename for plugin, or stylesheet for theme.
	 * @param array  $aData
	 * @param string $sContext
	 * @return $this
	 */
	public function updateItemInSnapshot( $sSlug, $aData, $sContext = 'plugins' ) {
		$aSnapshot = $this->loadSnapshot( $sContext );
		$aSnapshot[ $sSlug ] = $aData;
		return $this->storeSnapshot( $aSnapshot, $sContext );
	}

	protected function setupSnapshots() {
		$this->snapshotPlugins();
		$this->snapshotThemes();
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
			$aSnapshot[ $sSlug ] = array(
				'version' => $oTheme->get( 'Version' ),
				'ts'      => $this->loadDP()->time(),
				'hashes'  => $this->hashThemeFiles( $sSlug )
			);
		}
		$this->storeSnapshot( $aSnapshot, 'themes' );

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
	private function loadSnapshot( $sContext = 'plugins' ) {
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
		foreach ( $this->loadFS()->getFilesInDir( $sDir ) as $oFile ) {
			if ( $oFile->getExtension() == 'php' ) {
				$aSnaps[ $oFile->getFilename() ] = md5_file( $oFile->getPathname() );
			}
		}
		return $aSnaps;
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
	protected function scanPlugins() {
		return $this->runSnapshotScan( 'plugins' );
	}

	/**
	 * @return array[]
	 */
	protected function scanThemes() {
		return $this->runSnapshotScan( 'plugins' );
	}

	/**
	 * @param string $sContext
	 * @return array[]
	 */
	protected function runSnapshotScan( $sContext = 'plugins' ) {

		$aDifferences = array();
		$aUnrecognised = array();
		$aMissing = array();

		$aSnaps = $this->loadSnapshot( $sContext );
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
			if ( !empty( $aUnrecog ) ) {
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
		return $oFO->getPtlCronName();
	}

	/**
	 * @return string
	 */
	private function getSnapsBaseDir() {
		return path_join( WP_CONTENT_DIR, 'shield/locker' );
	}
}
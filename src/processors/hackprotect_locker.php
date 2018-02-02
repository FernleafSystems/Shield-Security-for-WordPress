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
//		$this->setupSnapshots();

		die();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
	}

	protected function setupSnapshots() {
		$this->snapshotPlugins();
		$this->snapshotThemes();
	}

	/**
	 * Guarded: Only ever snapshots when option is enabled.
	 * @return $this
	 */
	private function snapshotPlugins() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$oWpPl = $this->loadWpPlugins();
		$aPlugins = $oWpPl->getPlugins();

		$aSnapshot = array();
		foreach ( $aPlugins as $sBaseName => $aData ) {
			$aSnapshot[ $sBaseName ] = array(
				'version' => $aData[ 'Version' ],
				'ts'      => $this->loadDP()->time(),
				'hashes'  => $this->snapshotPlugin( $sBaseName )
			);
		}

		$this->storeSnapshot( $aSnapshot, 'plugins' );
		return $this;
	}

	/**
	 * Guarded: Only ever snapshots when option is enabled.
	 * @return $this
	 */
	private function snapshotThemes() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
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
				'hashes'  => $this->snapshotTheme( $oTheme )
			);
		}

		$this->storeSnapshot( $aSnapshot, 'themes' );
		return $this;
	}

	/**
	 * @param array  $aSnapshot
	 * @param string $sContext
	 */
	private function storeSnapshot( $aSnapshot, $sContext = 'plugins' ) {
		$oWpFs = $this->loadFS();
		$sDir = $this->getSnapsBaseDir();
		$sSnap = path_join( $sDir, $sContext.'.txt' );
		$oWpFs->mkdir( $sDir );
		$oWpFs->putFileContent( $sSnap, base64_encode( json_encode( $aSnapshot ) ) );
	}

	/**
	 * @param string $sContext
	 * @return array
	 */
	private function loadSnapshot( $sContext = 'plugins' ) {
		$aDecoded = array();

		$sSnap = path_join( $this->getSnapsBaseDir(), $sContext.'.txt' );

		$sRaw = $this->loadFS()->getFileContent( $sSnap );
		if ( !empty( $sRaw ) ) {
			$aDecoded = json_decode( base64_decode( $sRaw ), true );
		}
		return $aDecoded;
	}

	/**
	 * @param string $sBaseName
	 * @return string[]
	 */
	protected function snapshotPlugin( $sBaseName ) {

		$sDir = dirname( path_join( WP_PLUGIN_DIR, $sBaseName ) );

		$aSnaps = array();
		foreach ( $this->loadFS()->getFilesInDir( $sDir ) as $oFile ) {
			if ( $oFile->getExtension() == 'php' ) {
				$aSnaps[ $oFile->getFilename() ] = md5_file( $oFile->getPathname() );
			}
		}

		return $aSnaps;
	}

	/**
	 * @param WP_Theme $oTheme
	 * @return string[]
	 */
	protected function snapshotTheme( $oTheme ) {

		$aSnaps = array();
		foreach ( $this->loadFS()->getFilesInDir( $oTheme->get_stylesheet_directory() ) as $oFile ) {
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
<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class Scanner {

	/**
	 * @var array
	 */
	protected $aExclusions;

	/**
	 * @var array
	 */
	protected $aMissingExclusions;

	/**
	 * @var bool
	 */
	protected $bHardExcludePluginsThemes;

	/**
	 * @return ResultsSet
	 */
	public function run() {

		$oResultSet = new ResultsSet();
		$oHashes = Services::CoreFileHashes();
		if ( !$oHashes->isReady() ) {
			return $oResultSet;
		}

		$sRegExclusions = $this->getExclusionsRegex();
		$sRegMissingExcl = $this->getMissingExclusionsRegex();
		$bHasExclusions = !empty( $sRegExclusions );
		$bHasMissingExclusions = !empty( $sRegMissingExcl );
		foreach ( $oHashes->getHashes() as $sFragment => $sMd5HashWp ) {

			if ( $this->isHardExcludePluginsThemes() && strpos( $sFragment, 'wp-content/' ) === 0 ) {
				// To reduce noise, we exclude plugins and themes (by default)
				continue;
			}

			$oRes = new ResultItem();
			$oRes->md5_file_wp = $sMd5HashWp;
			$oRes->path_fragment = $sFragment;
			$oRes->path_full = $oHashes->getAbsolutePathFromFragment( $oRes->path_fragment );
			$oRes->is_missing = !Services::WpFs()->exists( $oRes->path_full );
			$oRes->is_checksumfail = !$oRes->is_missing && $this->isChecksumFail( $oRes );
			$oRes->is_excluded = ( $bHasExclusions && preg_match( $sRegExclusions, $oRes->path_fragment ) )
								 || ( $bHasMissingExclusions && preg_match( $sRegMissingExcl, $oRes->path_fragment ) );

			if ( $oRes->is_missing || $oRes->is_checksumfail ) {
				$oResultSet->addItem( $oRes );
			}
		}

		return $oResultSet;
	}

	/**
	 * @param ResultItem $oRes
	 * @return bool
	 */
	protected function isChecksumFail( $oRes ) {
		$bFail = false;
		if ( !$oRes->is_missing ) {
			$bFail = ( $oRes->md5_file_wp != md5_file( $oRes->path_full ) )
					 && ( strpos( $oRes->path_full, '.php' ) > 0 )
					 && ( $oRes->md5_file_wp != Services::DataManipulation()
														->convertLineEndingsDosToLinux( $oRes->path_full ) );
		}
		return $bFail;
	}

	/**
	 * @return string
	 */
	public function getExclusionsRegex() {
		$sPattern = '';
		if ( is_array( $this->aExclusions ) && !empty( $this->aExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$this->aExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}

	/**
	 * @return string
	 */
	public function getMissingExclusionsRegex() {
		$sPattern = '';
		if ( is_array( $this->aMissingExclusions ) && !empty( $this->aMissingExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$this->aMissingExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}

	/**
	 * @return bool
	 */
	public function isHardExcludePluginsThemes() {
		return (bool)( isset( $this->bHardExcludePluginsThemes ) ? $this->bHardExcludePluginsThemes : true );
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setExclusions( $aExclusions ) {
		$this->aExclusions = $aExclusions;
		return $this;
	}

	/**
	 * @param bool $bHardExcludePluginsThemes
	 * @return Scanner
	 */
	public function setIsHardExcludePluginsThemes( $bHardExcludePluginsThemes ) {
		$this->bHardExcludePluginsThemes = $bHardExcludePluginsThemes;
		return $this;
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setMissingExclusions( $aExclusions ) {
		$this->aMissingExclusions = $aExclusions;
		return $this;
	}
}
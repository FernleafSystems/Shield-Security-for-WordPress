<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class RetrieveForItem
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb
 */
class RetrieveForItem {

	const URL_API_ROOT = 'https://wpvulndb.com/api/v2/%s/%s';

	/**
	 * @var string
	 */
	protected $sContext;

	/**
	 * @var string
	 */
	protected $sCurrentVersion;

	/**
	 * @var string
	 */
	protected $sSlug;

	/**
	 * @return WpVulnVO[]
	 */
	public function retrieve() {
		$sSlug = $this->getSlug();

		$aD = $this->getCachedVo();
		if ( empty( $aD ) ) {
			$sRaw = Services::WpFs()->getUrlContent( $this->buildApiUrl() );
			if ( empty( $sRaw ) ) {
				$aD = array();
			}
			else {
				$aD = @json_decode( trim( $sRaw ), true );
				if ( !is_array( $aD ) || !isset( $aD[ $sSlug ] ) || !is_array( $aD[ $sSlug ] ) ) {
					$aD = array();
				}
				else {
					$aD = $aD[ $sSlug ];
				}
			}

			$aD[ 'slug' ] = $sSlug;
			if ( !isset( $aD[ 'vulnerabilities' ] ) || !is_array( $aD[ 'vulnerabilities' ] ) ) {
				$aD[ 'vulnerabilities' ] = array();
			}
			$this->setVoCache( $aD );
		}

		$aVulns = array_map(
			function ( $aVulnData ) {
				$oVo = ( new WpVulnVO() )->applyFromArray( $aVulnData );
				foreach ( [ 'created_at', 'updated_at', 'published_date' ] as $sKey ) {
					if ( empty( $oVo->{$sKey} ) || !is_numeric( $oVo->{$sKey} ) ) {
						$oVo->{$sKey} = strtotime( $oVo->{$sKey} );
					}
				}
				return $oVo;
			},
			$aD[ 'vulnerabilities' ]
		);

		return $aVulns;
	}

	/**
	 * @return array
	 */
	protected function getCachedVo() {
		$oWp = Services::WpGeneral();
		$aCacheData = $oWp->getTransient( $this->getVoCacheKey() );
		if ( !is_array( $aCacheData ) ) {
			$aCacheData = array();
			$this->setVoCache( $aCacheData );
		}
		return $aCacheData;
	}

	/**
	 * @param array $aCacheData
	 * @return $this
	 */
	protected function setVoCache( $aCacheData ) {
		Services::WpGeneral()->setTransient( $this->getVoCacheKey(), $aCacheData, DAY_IN_SECONDS );
		return $this;
	}

	/**
	 * @return string
	 */
	private function getVoCacheKey() {
		return 'wpvulndb-'.md5( __NAMESPACE__.$this->getContext().$this->getSlug() );
	}

	/**
	 * @return string
	 */
	protected function buildApiUrl() {
		return sprintf( self::URL_API_ROOT, $this->getContext(), $this->getSlug() );
	}

	/**
	 * @return string
	 */
	public function getContext() {
		return $this->sContext;
	}

	/**
	 * @return string
	 */
	public function getCurrentVersion() {
		return $this->sCurrentVersion;
	}

	/**
	 * @return string
	 */
	public function getSlug() {
		return $this->sSlug;
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function setContext( $sContext ) {
		$this->sContext = $sContext;
		return $this;
	}

	/**
	 * @param string $sVersion
	 * @return $this
	 */
	public function setCurrentVersion( $sVersion ) {
		$this->sCurrentVersion = $sVersion;
		return $this;
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function setSlug( $sContext ) {
		$this->sSlug = $sContext;
		return $this;
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\WordPressOrg;

use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Versions;

class PluginVersions {

	private const CACHE_TTL = 600;
	private const TRANSIENT_KEY_PREFIX = 'apto-shield-wporg-plugin-versions-';
	private const RELEASE_VERSION_REGEX = '#^\d+(\.\d+)+$#';

	private string $slug;

	/**
	 * @var array<array-key,mixed>|null
	 */
	private ?array $rawVersionUrls;

	/**
	 * @var array<string,string>|null
	 */
	private ?array $releaseUrls = null;

	/**
	 * @param array<array-key,mixed>|null $versionUrls
	 */
	public function __construct( string $slug, ?array $versionUrls = null ) {
		$this->slug = \trim( $slug );
		$this->rawVersionUrls = $versionUrls;
	}

	/**
	 * @return array<string,string>
	 */
	public function releaseUrls() :array {
		if ( $this->releaseUrls === null ) {
			if ( $this->rawVersionUrls !== null ) {
				$this->releaseUrls = $this->normalizeReleaseUrls( $this->rawVersionUrls );
			}
			elseif ( $this->slug === '' ) {
				$this->releaseUrls = [];
			}
			else {
				$this->releaseUrls = $this->loadCachedReleaseUrls();
				if ( $this->releaseUrls === null ) {
					$this->releaseUrls = $this->normalizeReleaseUrls( $this->loadVersionUrls() );
					Transient::Set( $this->cacheKey(), $this->releaseUrls, self::CACHE_TTL );
				}
			}
		}
		return $this->releaseUrls;
	}

	/**
	 * @return string[]
	 */
	public function releaseVersions() :array {
		return \array_keys( $this->releaseUrls() );
	}

	public function latestVersionNewerThan( string $currentVersion ) :?string {
		$latest = null;
		$currentVersion = self::normalizeReleaseVersion( $currentVersion );

		if ( $currentVersion !== '' ) {
			$newerVersions = \array_filter(
				$this->releaseVersions(),
				static fn( string $version ) => \version_compare( $version, $currentVersion, '>' )
			);
			if ( !empty( $newerVersions ) ) {
				$latest = \end( $newerVersions );
			}
		}

		return $latest === false ? null : $latest;
	}

	public function urlForVersion( string $version ) :string {
		return $this->releaseUrls()[ self::normalizeReleaseVersion( $version ) ] ?? '';
	}

	public function hasAtLeastTwoNewerMajorVersions( string $currentVersion ) :bool {
		$currentMajor = $this->majorVersionFrom( $currentVersion );
		if ( $currentMajor === null ) {
			return false;
		}

		$newerMajors = [];
		foreach ( $this->releaseVersions() as $version ) {
			$major = $this->majorVersionFrom( $version );
			if ( $major !== null && $major > $currentMajor ) {
				$newerMajors[ $major ] = true;
			}
		}

		return \count( $newerMajors ) >= 2;
	}

	public static function normalizeReleaseVersion( $version ) :string {
		if ( !\is_scalar( $version ) ) {
			return '';
		}

		$version = \trim( (string)$version );
		return \preg_match( self::RELEASE_VERSION_REGEX, $version ) === 1 ? $version : '';
	}

	/**
	 * @return array<array-key,mixed>
	 */
	protected function loadVersionUrls() :array {
		return ( new Versions() )
			->setWorkingSlug( $this->slug )
			->allVersionsUrls();
	}

	/**
	 * @return array<string,string>|null
	 */
	private function loadCachedReleaseUrls() :?array {
		$cached = Transient::Get( $this->cacheKey() );
		return \is_array( $cached ) ? $this->normalizeReleaseUrls( $cached ) : null;
	}

	private function cacheKey() :string {
		return self::TRANSIENT_KEY_PREFIX.\md5( $this->slug );
	}

	/**
	 * @param array<array-key,mixed> $versionUrls
	 * @return array<string,string>
	 */
	private function normalizeReleaseUrls( array $versionUrls ) :array {
		$releaseUrls = [];

		foreach ( $versionUrls as $version => $url ) {
			$version = self::normalizeReleaseVersion( $version );
			if ( $version !== '' && \is_string( $url ) ) {
				$url = \trim( $url );
				if ( $url !== '' ) {
					$releaseUrls[ $version ] = $url;
				}
			}
		}

		\uksort( $releaseUrls, 'version_compare' );

		return $releaseUrls;
	}

	private function majorVersionFrom( string $version ) :?int {
		$version = self::normalizeReleaseVersion( $version );
		return $version === '' ? null : \intval( \explode( '.', $version )[ 0 ] );
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

/**
 * @property list<string>       $file_exts
 * @property list<string>       $coverage_families
 * @property array<string, int> $scan_root_dirs
 * @property list<string>       $paths_whitelisted
 * @property string[]           $patterns_regex
 * @property string[]           $patterns_raw
 * @property string[]           $patterns_iraw
 * @property string[]           $patterns_functions
 * @property string[]           $patterns_keywords
 * @property string[]           $valid_files
 * @property positive-int       $max_file_size (bytes)
 * @property array{
 *     plugin:array<string,array{version:string,comparison_eligible:bool}>,
 *     theme:array<int|string,array{version:string,comparison_eligible:bool}>
 * } $asset_snapshot_eligibility
 * @property array{plugin:list<string>,theme:list<string>} $asset_comparison_incomplete
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO {
	public const DEFAULT_SLEEP_SECONDS = 0.1;
	public const DEFAULT_MAX_FILE_SIZE = 16*1024*1024;

	public const COVERAGE_FAMILY_CORE_INTEGRITY = 'core_integrity';
	public const COVERAGE_FAMILY_PLUGIN_INTEGRITY = 'plugin_integrity';
	public const COVERAGE_FAMILY_THEME_INTEGRITY = 'theme_integrity';
	public const COVERAGE_FAMILY_WPROOT_UNIDENTIFIED = 'wproot_unidentified';
	public const COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED = 'wpcontent_unidentified';
	public const COVERAGE_FAMILY_MALWARE = 'malware';

	public const COVERAGE_FAMILIES = [
		self::COVERAGE_FAMILY_CORE_INTEGRITY,
		self::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
		self::COVERAGE_FAMILY_THEME_INTEGRITY,
		self::COVERAGE_FAMILY_WPROOT_UNIDENTIFIED,
		self::COVERAGE_FAMILY_WPCONTENT_UNIDENTIFIED,
		self::COVERAGE_FAMILY_MALWARE,
	];

	public static function normalizeMaxFileSize( $value ) :int {
		return \is_int( $value ) && $value > 0 ? $value : self::DEFAULT_MAX_FILE_SIZE;
	}

	public function __set( string $key, $value ) {
		if ( $key === 'asset_snapshot_eligibility' ) {
			$value = self::normalizeAssetSnapshotEligibility( $value );
			if ( $value === null ) {
				parent::__unset( $key );
				return;
			}
		}
		parent::__set( $key, $value );
	}

	public function applyFromArray( array $data, array $restrictedKeys = [] ) {
		if ( empty( $restrictedKeys ) || \in_array( 'file_exts', $restrictedKeys, true ) ) {
			$extensions = $data[ 'file_exts' ] ?? null;
			$data[ 'file_exts' ] = \is_array( $extensions )
				? ( new NormalizeFileExtensions() )->run( $extensions )
				: [];
		}
		if ( empty( $restrictedKeys ) || \in_array( 'max_file_size', $restrictedKeys, true ) ) {
			$data[ 'max_file_size' ] = self::normalizeMaxFileSize( $data[ 'max_file_size' ] ?? null );
		}
		if ( empty( $restrictedKeys ) || \in_array( 'asset_snapshot_eligibility', $restrictedKeys, true ) ) {
			if ( \array_key_exists( 'asset_snapshot_eligibility', $data ) ) {
				$eligibility = self::normalizeAssetSnapshotEligibility( $data[ 'asset_snapshot_eligibility' ] );
				if ( $eligibility === null ) {
					unset( $data[ 'asset_snapshot_eligibility' ] );
				}
				else {
					$data[ 'asset_snapshot_eligibility' ] = $eligibility;
				}
			}
		}
		return parent::applyFromArray( $data, $restrictedKeys );
	}

	public function hasValidAssetSnapshotEligibility() :bool {
		return \is_array( parent::__get( 'asset_snapshot_eligibility' ) );
	}

	public function hasValidAssetComparisonIncomplete() :bool {
		$raw = $this->getRawData();
		return !\array_key_exists( 'asset_comparison_incomplete', $raw )
			   || self::isValidAssetComparisonIncomplete( $raw[ 'asset_comparison_incomplete' ] );
	}

	/**
	 * @return array{plugin:list<string>,theme:list<string>}
	 */
	public function getAssetComparisonIncomplete() :array {
		if ( !$this->hasValidAssetComparisonIncomplete() ) {
			throw new \UnexpectedValueException( 'Asset comparison incomplete metadata is malformed.' );
		}

		$value = parent::__get( 'asset_comparison_incomplete' );
		return \is_array( $value ) ? $value : [
			'plugin' => [],
			'theme'  => [],
		];
	}

	public function isAssetComparisonIncomplete( string $assetType, string $assetKey ) :bool {
		if ( !$this->isValidAssetReference( $assetType, $assetKey ) ) {
			return false;
		}
		return \in_array( $assetKey, $this->getAssetComparisonIncomplete()[ $assetType ], true );
	}

	public function markAssetComparisonIncomplete( string $assetType, string $assetKey ) :bool {
		if ( !$this->isValidAssetReference( $assetType, $assetKey ) ) {
			throw new \InvalidArgumentException( 'Asset comparison incomplete identity is invalid.' );
		}

		$incomplete = $this->getAssetComparisonIncomplete();
		if ( \in_array( $assetKey, $incomplete[ $assetType ], true ) ) {
			return false;
		}

		$incomplete[ $assetType ][] = $assetKey;
		parent::__set( 'asset_comparison_incomplete', $incomplete );
		return true;
	}

	public function isAssetSnapshotComparisonEligible(
		string $assetType,
		string $assetKey,
		string $assetVersion
	) :bool {
		if ( $this->scope_type !== 'full' ) {
			return true;
		}
		if ( !$this->hasValidAssetSnapshotEligibility()
			 || !$this->hasValidAssetComparisonIncomplete()
			 || !\in_array( $assetType, [ 'plugin', 'theme' ], true ) ) {
			return false;
		}
		if ( $this->isAssetComparisonIncomplete( $assetType, $assetKey ) ) {
			return false;
		}

		$entry = $this->asset_snapshot_eligibility[ $assetType ][ $assetKey ] ?? null;
		return \is_array( $entry )
			   && $entry[ 'version' ] === $assetVersion
			   && $entry[ 'comparison_eligible' ] === true;
	}

	/**
	 * @return list<array{0:string,1:string,2:string}>
	 */
	public function getComparisonEligibleAssetTuples() :array {
		$eligible = [];
		if ( !$this->hasValidAssetSnapshotEligibility() ) {
			return $eligible;
		}
		if ( !$this->hasValidAssetComparisonIncomplete() ) {
			return $eligible;
		}
		$incomplete = $this->getAssetComparisonIncomplete();

		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			foreach ( $this->asset_snapshot_eligibility[ $assetType ] as $assetKey => $entry ) {
				if ( $entry[ 'comparison_eligible' ]
					 && !\in_array( (string)$assetKey, $incomplete[ $assetType ], true ) ) {
					$eligible[] = [ $assetType, (string)$assetKey, $entry[ 'version' ] ];
				}
			}
		}
		return $eligible;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'valid_files':
				$value = \is_array( $value ) ? $value : [];
				break;
			default:
				break;
		}
		return $value;
	}

	/**
	 * @return array{
	 *     plugin:array<string,array{version:string,comparison_eligible:bool}>,
	 *     theme:array<int|string,array{version:string,comparison_eligible:bool}>
	 * }|null
	 */
	private static function normalizeAssetSnapshotEligibility( $value ) :?array {
		if ( !\is_array( $value ) ) {
			return null;
		}

		$topLevelKeys = \array_keys( $value );
		\sort( $topLevelKeys, \SORT_STRING );
		if ( $topLevelKeys !== [ 'plugin', 'theme' ] ) {
			return null;
		}

		$normalized = [
			'plugin' => [],
			'theme'  => [],
		];
		foreach ( \array_keys( $normalized ) as $assetType ) {
			if ( !\is_array( $value[ $assetType ] ) ) {
				return null;
			}
			foreach ( $value[ $assetType ] as $assetKey => $entry ) {
				if ( $assetType === 'plugin' && !\is_string( $assetKey ) ) {
					return null;
				}
				$assetKey = (string)$assetKey;
				if ( !self::isValidExactString( $assetKey )
					 || !\is_array( $entry ) ) {
					return null;
				}

				$entryKeys = \array_keys( $entry );
				\sort( $entryKeys, \SORT_STRING );
				if ( $entryKeys !== [ 'comparison_eligible', 'version' ]
					 || !\is_string( $entry[ 'version' ] )
					 || !self::isValidExactString( $entry[ 'version' ] )
					 || !\is_bool( $entry[ 'comparison_eligible' ] ) ) {
					return null;
				}

				$normalized[ $assetType ][ $assetKey ] = [
					'version'             => $entry[ 'version' ],
					'comparison_eligible' => $entry[ 'comparison_eligible' ],
				];
			}
		}
		return $normalized;
	}

	private static function isValidAssetComparisonIncomplete( $value ) :bool {
		if ( !\is_array( $value ) ) {
			return false;
		}

		$topLevelKeys = \array_keys( $value );
		\sort( $topLevelKeys, \SORT_STRING );
		if ( $topLevelKeys !== [ 'plugin', 'theme' ] ) {
			return false;
		}

		foreach ( [ 'plugin', 'theme' ] as $assetType ) {
			$keys = $value[ $assetType ];
			if ( !\is_array( $keys )
				 || ( !empty( $keys ) && \array_keys( $keys ) !== \range( 0, \count( $keys ) - 1 ) ) ) {
				return false;
			}
			foreach ( $keys as $assetKey ) {
				if ( !\is_string( $assetKey ) || !self::isValidExactString( $assetKey ) ) {
					return false;
				}
			}
			if ( \count( $keys ) !== \count( \array_unique( $keys, \SORT_STRING ) ) ) {
				return false;
			}
		}

		return true;
	}

	private function isValidAssetReference( string $assetType, string $assetKey ) :bool {
		return \in_array( $assetType, [ 'plugin', 'theme' ], true )
			   && self::isValidExactString( $assetKey );
	}

	private static function isValidExactString( string $value ) :bool {
		return \trim( $value ) !== '' && \strpos( $value, "\0" ) === false;
	}
}

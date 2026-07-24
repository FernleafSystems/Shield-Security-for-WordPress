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
 */
class ScanActionVO extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO {
	public const DEFAULT_SLEEP_SECONDS = 0.1;

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
}

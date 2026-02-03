<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling;

use RuntimeException;

/**
 * Merges modular configuration files from plugin-spec/ into a single plugin.json.
 *
 * Merge Rules:
 * - Arrays (sections, options): Concatenated with duplicate key/slug validation
 * - Objects: Merged using array_replace_recursive
 *
 * Output Format: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
 */
class ConfigMerger {

	/**
	 * File manifest defining the expected spec files and their target keys.
	 * Order matters for deterministic output.
	 *
	 * @var array<string, array{target: string, type: string}>
	 */
	private const FILE_MANIFEST = [
		'01_properties.json'     => [ 'target' => 'properties', 'type' => 'object' ],
		'04_requirements.json'   => [ 'target' => 'requirements', 'type' => 'object' ],
		'07_paths.json'          => [ 'target' => 'paths', 'type' => 'object' ],
		'10_includes.json'       => [ 'target' => 'includes', 'type' => 'object' ],
		'13_menu.json'           => [ 'target' => 'menu', 'type' => 'object' ],
		'16_labels.json'         => [ 'target' => 'labels', 'type' => 'object' ],
		'19_meta.json'           => [ 'target' => 'meta', 'type' => 'object' ],
		'22_plugin_meta.json'    => [ 'target' => 'plugin_meta', 'type' => 'object' ],
		'25_action_links.json'   => [ 'target' => 'action_links', 'type' => 'object' ],
		'28_modules.json'        => [ 'target' => 'config_spec.modules', 'type' => 'object' ],
		'31_sections.json'       => [ 'target' => 'config_spec.sections', 'type' => 'array' ],
		'34_options.json'        => [ 'target' => 'config_spec.options', 'type' => 'array' ],
		'37_defs.json'           => [ 'target' => 'config_spec.defs', 'type' => 'object' ],
		'40_admin_notices.json'  => [ 'target' => 'config_spec.admin_notices', 'type' => 'object' ],
		'43_databases.json'      => [ 'target' => 'config_spec.databases', 'type' => 'object' ],
		'46_events.json'         => [ 'target' => 'config_spec.events', 'type' => 'object' ],
		'50_translations.json'   => [ 'target' => 'translations', 'type' => 'object' ],
	];

	/**
	 * Required keys in the final merged output.
	 *
	 * @var string[]
	 */
	private const REQUIRED_OUTPUT_KEYS = [
		'properties',
		'requirements',
		'paths',
		'includes',
		'menu',
		'labels',
		'meta',
		'plugin_meta',
		'action_links',
		'config_spec',
	];

	/**
	 * Merge all spec files from a directory into a configuration array.
	 *
	 * @param string $specDir Path to the plugin-spec directory
	 * @return array The merged configuration
	 * @throws RuntimeException If any validation fails
	 */
	public function merge( string $specDir ) :array {
		$this->validateSpecDirectory( $specDir );

		$config = [];
		$optionKeys = [];
		$sectionSlugs = [];

		foreach ( self::FILE_MANIFEST as $filename => $meta ) {
			$filePath = $specDir . DIRECTORY_SEPARATOR . $filename;
			$this->validateFileExists( $filePath, $filename );

			$data = $this->loadJsonFile( $filePath, $filename );
			$this->validateFileNotEmpty( $data, $filename );

			// Handle nested targets like 'config_spec.modules'
			$targetParts = explode( '.', $meta['target'] );

			if ( $meta['type'] === 'array' ) {
				// Validate no duplicates in arrays
				if ( $meta['target'] === 'config_spec.options' ) {
					$this->validateNoDuplicateOptionKeys( $data, $optionKeys, $filename );
				}
				elseif ( $meta['target'] === 'config_spec.sections' ) {
					$this->validateNoDuplicateSectionSlugs( $data, $sectionSlugs, $filename );
				}
			}

			// Merge into config
			$this->setNestedValue( $config, $targetParts, $data, $meta['type'] );
		}

		$this->validateRequiredKeys( $config );

		return $config;
	}

	/**
	 * Merge spec files and write the result to a file.
	 *
	 * @param string $specDir   Path to the plugin-spec directory
	 * @param string $outputPath Path to the output plugin.json file
	 * @throws RuntimeException If merge or write fails
	 */
	public function mergeToFile( string $specDir, string $outputPath ) :void {
		$config = $this->merge( $specDir );

		$json = json_encode(
			$config,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( $json === false ) {
			throw new RuntimeException(
				sprintf( 'Failed to encode merged configuration to JSON: %s', json_last_error_msg() )
			);
		}

		$bytesWritten = file_put_contents( $outputPath, $json . "\n" );

		if ( $bytesWritten === false ) {
			throw new RuntimeException(
				sprintf( 'Failed to write merged configuration to: %s', $outputPath )
			);
		}
	}

	/**
	 * Validate that the spec directory exists and is readable.
	 */
	private function validateSpecDirectory( string $specDir ) :void {
		if ( !is_dir( $specDir ) ) {
			throw new RuntimeException(
				sprintf( 'Spec directory does not exist: %s', $specDir )
			);
		}

		if ( !is_readable( $specDir ) ) {
			throw new RuntimeException(
				sprintf( 'Spec directory is not readable: %s', $specDir )
			);
		}
	}

	/**
	 * Validate that a required spec file exists.
	 */
	private function validateFileExists( string $filePath, string $filename ) :void {
		if ( !file_exists( $filePath ) ) {
			throw new RuntimeException(
				sprintf( 'Required spec file missing: %s', $filename )
			);
		}
	}

	/**
	 * Load and parse a JSON file.
	 *
	 * @return array The parsed JSON data
	 */
	private function loadJsonFile( string $filePath, string $filename ) :array {
		$content = file_get_contents( $filePath );

		if ( $content === false ) {
			throw new RuntimeException(
				sprintf( 'Failed to read spec file: %s', $filename )
			);
		}

		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new RuntimeException(
				sprintf( 'Invalid JSON in %s: %s', $filename, json_last_error_msg() )
			);
		}

		return $data;
	}

	/**
	 * Validate that a file's data is not empty.
	 */
	private function validateFileNotEmpty( array $data, string $filename ) :void {
		if ( empty( $data ) ) {
			throw new RuntimeException(
				sprintf( 'Spec file is empty or has no data: %s', $filename )
			);
		}
	}

	/**
	 * Validate no duplicate option keys across option arrays.
	 *
	 * @param array<int, array> $options      The options array from the current file
	 * @param array<string, string> &$seenKeys Track of seen keys (key => source filename)
	 * @param string $filename Current file being processed
	 */
	private function validateNoDuplicateOptionKeys( array $options, array &$seenKeys, string $filename ) :void {
		foreach ( $options as $option ) {
			if ( !isset( $option['key'] ) ) {
				continue;
			}

			$key = $option['key'];

			if ( isset( $seenKeys[$key] ) ) {
				throw new RuntimeException(
					sprintf(
						'Duplicate option key "%s" found in %s (previously defined in %s)',
						$key,
						$filename,
						$seenKeys[$key]
					)
				);
			}

			$seenKeys[$key] = $filename;
		}
	}

	/**
	 * Validate no duplicate section slugs across section arrays.
	 *
	 * @param array<int, array> $sections      The sections array from the current file
	 * @param array<string, string> &$seenSlugs Track of seen slugs (slug => source filename)
	 * @param string $filename Current file being processed
	 */
	private function validateNoDuplicateSectionSlugs( array $sections, array &$seenSlugs, string $filename ) :void {
		foreach ( $sections as $section ) {
			if ( !isset( $section['slug'] ) ) {
				continue;
			}

			$slug = $section['slug'];

			if ( isset( $seenSlugs[$slug] ) ) {
				throw new RuntimeException(
					sprintf(
						'Duplicate section slug "%s" found in %s (previously defined in %s)',
						$slug,
						$filename,
						$seenSlugs[$slug]
					)
				);
			}

			$seenSlugs[$slug] = $filename;
		}
	}

	/**
	 * Set a value at a nested path in the config array.
	 *
	 * @param array $config The configuration array (by reference)
	 * @param string[] $path The path parts (e.g., ['config_spec', 'modules'])
	 * @param array $value The value to set
	 * @param string $mergeType 'object' for array_replace_recursive, 'array' for concatenation
	 */
	private function setNestedValue( array &$config, array $path, array $value, string $mergeType ) :void {
		$current = &$config;

		// Navigate to parent, creating intermediaries as needed
		$lastKey = array_pop( $path );

		foreach ( $path as $key ) {
			if ( !isset( $current[$key] ) ) {
				$current[$key] = [];
			}
			$current = &$current[$key];
		}

		// Merge or set the value
		if ( !isset( $current[$lastKey] ) ) {
			$current[$lastKey] = $value;
		}
		elseif ( $mergeType === 'array' ) {
			// Concatenate arrays
			$current[$lastKey] = array_merge( $current[$lastKey], $value );
		}
		else {
			// Object merge (recursive replace)
			$current[$lastKey] = array_replace_recursive( $current[$lastKey], $value );
		}
	}

	/**
	 * Validate that all required top-level keys exist in the merged output.
	 */
	private function validateRequiredKeys( array $config ) :void {
		foreach ( self::REQUIRED_OUTPUT_KEYS as $key ) {
			if ( !isset( $config[$key] ) ) {
				throw new RuntimeException(
					sprintf( 'Required key "%s" missing from merged configuration', $key )
				);
			}
		}
	}

	/**
	 * Get the file manifest for external use (e.g., splitting).
	 *
	 * @return array<string, array{target: string, type: string}>
	 */
	public static function getFileManifest() :array {
		return self::FILE_MANIFEST;
	}
}


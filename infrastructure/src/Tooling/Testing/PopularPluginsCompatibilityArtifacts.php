<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class PopularPluginsCompatibilityArtifacts extends WordPressPackageRuntimeArtifacts {

	public const SUMMARY_FILE = 'popular-plugin-compat-summary.json';
	public const COMPANION_PLUGINS_FILE = 'companion-plugins.json';
	public const ACTIVATION_RESULTS_FILE = 'activation-results.json';

	private const DEFAULT_DIR = 'tmp/popular-plugin-compat';

	public static function resolve( string $rootDir, ?string $explicitDir, bool $mirrorOutput = false ) :self {
		return new self(
			self::resolveRuntimeDir(
				$rootDir,
				$explicitDir,
				'SHIELD_POPULAR_PLUGIN_TEST_ARTIFACT_DIR',
				self::DEFAULT_DIR
			),
			$mirrorOutput,
			self::SUMMARY_FILE,
			[
				'companion_plugins'  => self::COMPANION_PLUGINS_FILE,
				'activation_results' => self::ACTIVATION_RESULTS_FILE,
			]
		);
	}
}

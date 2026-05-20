<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class PublicUpgradeArtifacts extends WordPressPackageRuntimeArtifacts {

	public const SUMMARY_FILE = 'upgrade-public-summary.json';
	public const PUBLIC_VERSION_FILE = 'public-version.json';
	public const PRIMING_REPORT_FILE = 'priming-report.json';
	public const UPDATE_RESULT_FILE = 'update-result.json';

	private const DEFAULT_DIR = 'tmp/upgrade-public';

	public static function resolve( string $rootDir, ?string $explicitDir, bool $mirrorOutput = false ) :self {
		return new self(
			self::resolveRuntimeDir(
				$rootDir,
				$explicitDir,
				'SHIELD_UPGRADE_TEST_ARTIFACT_DIR',
				self::DEFAULT_DIR
			),
			$mirrorOutput,
			self::SUMMARY_FILE,
			[
				'public_version' => self::PUBLIC_VERSION_FILE,
				'priming_report' => self::PRIMING_REPORT_FILE,
				'update_result'  => self::UPDATE_RESULT_FILE,
			]
		);
	}
}

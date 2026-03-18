<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class BrowserTestLane {

	private ProcessRunner $processRunner;

	private LocalDevSiteManager $siteManager;

	public function __construct( ?ProcessRunner $processRunner = null, ?LocalDevSiteManager $siteManager = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->siteManager = $siteManager ?? new LocalDevSiteManager();
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	public function run( string $rootDir, array $playwrightArgs = [] ) :int {
		echo 'Mode: browser'.\PHP_EOL;

		$this->siteManager->ensureReady( $rootDir, true, true );

		$envOverrides = [
			'SHIELD_BROWSER_BASE_URL' => LocalDevSiteManager::SITE_URL,
		];

		return $this->processRunner->runForExitCode(
			\array_merge(
				[
					\PHP_BINARY,
					'./bin/run-node-tool.php',
					'playwright',
					'test',
				],
				$playwrightArgs
			),
			$rootDir,
			null,
			$envOverrides
		);
	}
}

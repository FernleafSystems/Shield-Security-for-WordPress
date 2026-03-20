<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class BrowserTestLane {

	private ProcessRunner $processRunner;

	private LocalSiteManager $siteManager;

	public function __construct( ?ProcessRunner $processRunner = null, ?LocalSiteManager $siteManager = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->siteManager = $siteManager ?? new LocalSiteManager( LocalSiteDefinitions::test() );
	}

	/**
	 * @param string[] $playwrightArgs
	 */
	public function run( string $rootDir, array $playwrightArgs = [] ) :int {
		echo 'Mode: browser'.\PHP_EOL;

		if ( $this->siteManager->reset( $rootDir ) !== 0 ) {
			return 1;
		}

		$envOverrides = [
			'SHIELD_BROWSER_BASE_URL' => $this->siteManager->definition()->siteUrl(),
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

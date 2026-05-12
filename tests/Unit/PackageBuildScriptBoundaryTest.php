<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PackageBuildScriptBoundaryTest extends TestCase {

	public function testPackageBuildScriptsDoNotDependOnTestPackagerConfig() :void {
		$projectRoot = Path::normalize( \dirname( __DIR__, 2 ) );
		$forbiddenNamespace = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Tests\\Helpers\\PackagerConfig';

		foreach ( [
			'bin/build-zip.php',
			'bin/package-plugin.php',
			'bin/run-strauss-dev.php',
		] as $relativePath ) {
			$content = \file_get_contents( Path::join( $projectRoot, $relativePath ) );
			$this->assertNotFalse( $content );
			$this->assertStringNotContainsString(
				$forbiddenNamespace,
				(string)$content,
				$relativePath.' must not depend on test autoload classes.'
			);
		}
	}
}

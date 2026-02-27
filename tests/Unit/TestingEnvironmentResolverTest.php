<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;

class TestingEnvironmentResolverTest extends TestCase {

	public function testBuildDockerProcessEnvOverridesWithoutPackageUnset() :void {
		$resolver = new TestingEnvironmentResolver();

		$envOverrides = $resolver->buildDockerProcessEnvOverrides( 'shield-tests' );

		$this->assertSame(
			[
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'COMPOSE_PROJECT_NAME' => 'shield-tests',
			],
			$envOverrides
		);
	}

	public function testBuildDockerProcessEnvOverridesWithPackageUnset() :void {
		$resolver = new TestingEnvironmentResolver();

		$envOverrides = $resolver->buildDockerProcessEnvOverrides( 'shield-local-db', true );

		$this->assertSame(
			[
				'DOCKER_BUILDKIT' => '1',
				'MSYS_NO_PATHCONV' => '1',
				'COMPOSE_PROJECT_NAME' => 'shield-local-db',
				'SHIELD_PACKAGE_PATH' => false,
			],
			$envOverrides
		);
	}
}

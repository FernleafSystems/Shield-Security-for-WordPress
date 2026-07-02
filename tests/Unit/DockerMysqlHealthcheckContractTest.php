<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DockerMysqlHealthcheckContractTest extends TestCase {

	/**
	 * @dataProvider provideDockerMysqlHealthcheckFiles
	 */
	public function test_mysql_healthchecks_use_tcp_ready_probe( string $relativePath, int $expectedProbeCount ) :void {
		$content = $this->readProjectFile( $relativePath );
		$this->assertSame( $expectedProbeCount, \substr_count( $content, '"mysqladmin", "ping", "--protocol=tcp", "-h", "127.0.0.1"' ) );
		$this->assertStringNotContainsString( '"mysqladmin", "ping", "-h", "localhost"', $content );
	}

	public function test_mysql_monitoring_script_uses_tcp_ready_probe() :void {
		$content = $this->readProjectFile( 'bin/test-mysql-monitoring.sh' );

		$this->assertStringContainsString( 'mysqladmin ping --protocol=tcp -h 127.0.0.1 --silent', $content );
		$this->assertStringContainsString( 'mysql --protocol=tcp -h 127.0.0.1', $content );
		$this->assertStringNotContainsString( 'mysqladmin ping -h localhost', $content );
	}

	public function test_docker_test_runner_uses_tcp_ping_and_sql_readiness() :void {
		$content = $this->readProjectFile( 'bin/run-tests-docker.sh' );

		$this->assertStringContainsString( 'MYSQL_PING_CMD=(mysqladmin ping --protocol=tcp -h"$DB_HOST" -u"$DB_USER")', $content );
		$this->assertStringContainsString( 'MYSQL_SELECT_CMD=(mysql --protocol=tcp -h"$DB_HOST" -u"$DB_USER")', $content );
		$this->assertStringContainsString( 'MYSQL_SELECT_CMD+=(-e "SELECT 1" "$DB_NAME")', $content );
		$this->assertStringNotContainsString( 'MYSQL_CMD="mysqladmin ping -h', $content );
		$this->assertStringNotContainsString( 'eval "$MYSQL_CMD"', $content );
	}

	/**
	 * @return array<string,array{0:string,1:int}>
	 */
	public static function provideDockerMysqlHealthcheckFiles() :array {
		return [
			'source runtime' => [ 'tests/docker/docker-compose.yml', 2 ],
			'integration local' => [ 'tests/docker/docker-compose.local-db.yml', 1 ],
			'local site' => [ 'tests/docker/docker-compose.local-site.yml', 1 ],
			'cross-site' => [ 'tests/docker/docker-compose.cross-site.yml', 1 ],
			'browser db' => [ 'tests/docker/docker-compose.browser-db.yml', 1 ],
			'upgrade public' => [ 'tests/docker/docker-compose.upgrade-public.yml', 1 ],
		];
	}

	private function readProjectFile( string $relativePath ) :string {
		$path = \dirname( __DIR__, 2 ).'/'.$relativePath;
		$this->assertFileExists( $path );

		return (string)\file_get_contents( $path );
	}
}

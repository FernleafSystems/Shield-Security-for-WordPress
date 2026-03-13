<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OperationalIssuesProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class OperationalIssuesProviderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_build_queue_items_maps_active_maintenance_states() :void {
		$provider = new OperationalIssuesProviderTestDouble( [
			'wp_updates' => [
				'key'           => 'wp_updates',
				'label'         => 'WordPress Version',
				'description'   => 'There is an upgrade available for WordPress.',
				'count'         => 1,
				'ignored_count' => 0,
				'severity'      => 'warning',
				'href'          => '/wp-admin/update-core.php',
				'action'        => 'Update',
				'target'        => '_blank',
			],
			'system_lib_openssl' => [
				'key'           => 'system_lib_openssl',
				'label'         => 'OpenSSL Extension',
				'description'   => 'OpenSSL requires review.',
				'count'         => 1,
				'ignored_count' => 0,
				'severity'      => 'warning',
				'href'          => 'https://www.openssl.org/news/vulnerabilities.html',
				'action'        => 'Review',
				'target'        => '_blank',
			],
			'wp_db_password' => [
				'key'           => 'wp_db_password',
				'label'         => 'MySQL DB Password',
				'description'   => 'Database password is weak.',
				'count'         => 1,
				'ignored_count' => 0,
				'severity'      => 'warning',
				'href'          => '',
				'action'        => 'Fix',
				'target'        => '',
			],
		] );

		$items = $provider->buildQueueItems();
		$this->assertSame( [ 'wp_updates', 'system_lib_openssl', 'wp_db_password' ], \array_column( $items, 'key' ) );
		$this->assertSame( [ 'maintenance', 'maintenance', 'maintenance' ], \array_column( $items, 'zone' ) );
		$this->assertSame( [ 1, 1, 1 ], \array_column( $items, 'count' ) );
		$this->assertSame( [ 'Update', 'Review', 'Fix' ], \array_column( $items, 'action' ) );
		$this->assertSame( [ '_blank', '_blank', '' ], \array_column( $items, 'target' ) );
		$this->assertSame( '', $items[ 2 ][ 'href' ] ?? 'missing' );
	}

	public function test_build_queue_items_skips_zero_count_states() :void {
		$provider = new OperationalIssuesProviderTestDouble( [
			'system_php_version' => [
				'key'           => 'system_php_version',
				'label'         => 'PHP Version',
				'description'   => 'This maintenance item is currently ignored.',
				'count'         => 0,
				'ignored_count' => 1,
				'severity'      => 'good',
				'href'          => 'https://example.com/php',
				'action'        => 'Review',
				'target'        => '_blank',
			],
			'system_ssl_certificate' => [
				'key'           => 'system_ssl_certificate',
				'label'         => 'SSL Certificate',
				'description'   => 'SSL certificate has expired.',
				'count'         => 1,
				'ignored_count' => 0,
				'severity'      => 'critical',
				'href'          => 'https://example.com/ssl',
				'action'        => 'Review',
				'target'        => '_blank',
			],
		] );

		$items = $provider->buildQueueItems();
		$this->assertCount( 1, $items );
		$this->assertSame( 'system_ssl_certificate', $items[ 0 ][ 'key' ] ?? '' );
		$this->assertSame( 'critical', $items[ 0 ][ 'severity' ] ?? '' );
		$this->assertSame( '_blank', $items[ 0 ][ 'target' ] ?? '' );
	}
}

class OperationalIssuesProviderTestDouble extends OperationalIssuesProvider {

	private array $states;

	public function __construct( array $states ) {
		$this->states = $states;
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new class( $this->states ) extends MaintenanceIssueStateProvider {
			private array $states;

			public function __construct( array $states ) {
				$this->states = $states;
			}

			public function buildStates() :array {
				return $this->states;
			}
		};
	}
}

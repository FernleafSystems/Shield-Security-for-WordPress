<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\OperationalIssuesProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class OperationalIssuesProviderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_build_queue_items_uses_maintenance_definitions_as_the_single_source_of_truth() :void {
		$provider = new OperationalIssuesProviderTestDouble(
			[
				[
					'key'             => 'wp_updates',
					'zone'            => 'maintenance',
					'component_class' => 'wp-updates',
				],
				[
					'key'             => 'system_lib_openssl',
					'zone'            => 'maintenance',
					'component_class' => 'openssl',
				],
				[
					'key'             => 'wp_db_password',
					'zone'            => 'maintenance',
					'component_class' => 'db-password',
				],
				[
					'key'             => 'wp_files',
					'zone'            => 'scans',
					'component_class' => 'scan-core',
				],
			],
			[
				'wp-updates' => [
					'title'           => 'WordPress Version',
					'desc_unprotected'=> 'There is an upgrade available for WordPress.',
					'is_protected'    => false,
					'is_critical'     => false,
					'is_applicable'   => true,
					'href_full'       => '/wp-admin/update-core.php',
					'href_full_target_blank' => true,
					'fix'             => 'Update',
				],
				'openssl' => [
					'title'           => 'OpenSSL Extension',
					'desc_unprotected'=> 'OpenSSL requires review.',
					'is_protected'    => false,
					'is_critical'     => false,
					'is_applicable'   => true,
					'href_full'       => 'https://www.openssl.org/news/vulnerabilities.html',
					'href_full_target_blank' => true,
					'fix'             => 'Review',
				],
				'db-password' => [
					'title'           => 'MySQL DB Password',
					'desc_unprotected'=> 'Database password is weak.',
					'is_protected'    => false,
					'is_critical'     => false,
					'is_applicable'   => true,
					'href_full'       => '',
					'fix'             => '',
				],
			],
			[
				'wp_updates'          => 1,
				'system_lib_openssl'  => 1,
				'wp_db_password'      => 1,
			]
		);

		$items = $provider->buildQueueItems();
		$this->assertSame( [ 'wp_updates', 'system_lib_openssl', 'wp_db_password' ], \array_column( $items, 'key' ) );
		$this->assertSame( [ 'maintenance', 'maintenance', 'maintenance' ], \array_column( $items, 'zone' ) );
		$this->assertSame( [ 1, 1, 1 ], \array_column( $items, 'count' ) );
		$this->assertSame( [ 'Update', 'Review', 'Fix' ], \array_column( $items, 'action' ) );
		$this->assertSame( [ '_blank', '_blank', '' ], \array_column( $items, 'target' ) );
		$this->assertSame( '', $items[ 2 ][ 'href' ] ?? 'missing' );
	}

	public function test_build_queue_items_skips_protected_non_applicable_and_zero_count_components() :void {
		$provider = new OperationalIssuesProviderTestDouble(
			[
				[
					'key'             => 'system_php_version',
					'zone'            => 'maintenance',
					'component_class' => 'php-version',
				],
				[
					'key'             => 'wp_db_password',
					'zone'            => 'maintenance',
					'component_class' => 'db-password',
				],
				[
					'key'             => 'wp_plugins_updates',
					'zone'            => 'maintenance',
					'component_class' => 'plugin-updates',
				],
				[
					'key'             => 'system_ssl_certificate',
					'zone'            => 'maintenance',
					'component_class' => 'ssl-certificate',
				],
			],
			[
				'php-version' => [
					'title'           => 'PHP Version',
					'desc_unprotected'=> 'PHP is old.',
					'is_protected'    => true,
					'is_critical'     => false,
					'is_applicable'   => true,
					'href_full'       => 'https://example.com/php',
					'fix'             => 'Review',
				],
				'db-password' => [
					'title'           => 'MySQL DB Password',
					'desc_unprotected'=> 'Database password is weak.',
					'is_protected'    => false,
					'is_critical'     => false,
					'is_applicable'   => false,
					'href_full'       => 'https://example.com/db',
					'fix'             => 'Review',
				],
				'plugin-updates' => [
					'title'           => 'Plugins With Updates',
					'desc_unprotected'=> 'Plugins need updates.',
					'is_protected'    => false,
					'is_critical'     => false,
					'is_applicable'   => true,
					'href_full'       => '/wp-admin/plugins.php',
					'fix'             => 'Update',
				],
				'ssl-certificate' => [
					'title'           => 'SSL Certificate',
					'desc_unprotected'=> 'SSL certificate has expired.',
					'is_protected'    => false,
					'is_critical'     => true,
					'is_applicable'   => true,
					'href_full'       => 'https://example.com/ssl',
					'href_full_target_blank' => true,
					'fix'             => 'Review',
				],
			],
			[
				'wp_plugins_updates'   => 0,
				'system_ssl_certificate' => 1,
			]
		);

		$items = $provider->buildQueueItems();
		$this->assertCount( 1, $items );
		$this->assertSame( 'system_ssl_certificate', $items[ 0 ][ 'key' ] ?? '' );
		$this->assertSame( 'critical', $items[ 0 ][ 'severity' ] ?? '' );
		$this->assertSame( '_blank', $items[ 0 ][ 'target' ] ?? '' );
	}
}

class OperationalIssuesProviderTestDouble extends OperationalIssuesProvider {

	private array $definitions;
	private array $components;
	private array $counts;

	public function __construct( array $definitions, array $components, array $counts ) {
		$this->definitions = $definitions;
		$this->components = $components;
		$this->counts = $counts;
	}

	protected function getDefinitions() :array {
		return $this->definitions;
	}

	protected function buildComponent( string $componentClass ) :array {
		return $this->components[ $componentClass ] ?? [];
	}

	protected function countForKey( string $key ) :int {
		return (int)( $this->counts[ $key ] ?? 0 );
	}
}

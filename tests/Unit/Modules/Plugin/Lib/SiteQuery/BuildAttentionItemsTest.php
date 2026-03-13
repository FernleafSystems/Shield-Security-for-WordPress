<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;

class BuildAttentionItemsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_preserves_machine_contract_fields() :void {
		$query = ( new BuildAttentionItemsTestDouble(
			[],
			[
				[
					'key'                => 'system_ssl_certificate',
					'zone'               => 'maintenance',
					'source'             => 'maintenance',
					'label'              => 'SSL Certificate',
					'description'        => 'SSL certificate requires review.',
					'count'              => 1,
					'ignored_count'      => 2,
					'severity'           => 'warning',
					'href'               => 'https://example.com/ssl',
					'action'             => 'Review',
					'target'             => '_blank',
					'supports_sub_items' => false,
				],
			]
		) )->build();

		$this->assertSame( 1700000000, $query[ 'generated_at' ] );
		$this->assertSame( 'SSL Certificate', $query[ 'items' ][ 0 ][ 'label' ] );
		$this->assertSame( 'SSL certificate requires review.', $query[ 'items' ][ 0 ][ 'description' ] );
		$this->assertSame( 2, $query[ 'items' ][ 0 ][ 'ignored_count' ] );
		$this->assertSame( '_blank', $query[ 'items' ][ 0 ][ 'target' ] );
		$this->assertFalse( $query[ 'items' ][ 0 ][ 'supports_sub_items' ] );
	}

	public function test_empty_items_are_all_clear() :void {
		$query = ( new BuildAttentionItemsTestDouble( [], [] ) )->build();

		$this->assertSame( 0, $query[ 'summary' ][ 'total' ] );
		$this->assertSame( 'good', $query[ 'summary' ][ 'severity' ] );
		$this->assertTrue( $query[ 'summary' ][ 'is_all_clear' ] );
		$this->assertSame( 0, $query[ 'groups' ][ 'scans' ][ 'total' ] );
		$this->assertSame( 0, $query[ 'groups' ][ 'maintenance' ][ 'total' ] );
	}

	public function test_items_are_sorted_by_severity_count_and_key_priority() :void {
		$query = ( new BuildAttentionItemsTestDouble(
			[
				[
					'key'                => 'plugin_files',
					'zone'               => 'scans',
					'source'             => 'scan',
					'label'              => 'Plugins',
					'description'        => 'Plugin files need review.',
					'count'              => 4,
					'ignored_count'      => 0,
					'severity'           => 'warning',
					'href'               => '/plugins',
					'action'             => 'Open',
					'target'             => '',
					'supports_sub_items' => false,
				],
				[
					'key'                => 'malware',
					'zone'               => 'scans',
					'source'             => 'scan',
					'label'              => 'Malware',
					'description'        => 'Malware found.',
					'count'              => 1,
					'ignored_count'      => 0,
					'severity'           => 'critical',
					'href'               => '/malware',
					'action'             => 'Open',
					'target'             => '',
					'supports_sub_items' => false,
				],
			],
			[
				[
					'key'                => 'wp_updates',
					'zone'               => 'maintenance',
					'source'             => 'maintenance',
					'label'              => 'WordPress Version',
					'description'        => 'Updates available.',
					'count'              => 6,
					'ignored_count'      => 0,
					'severity'           => 'warning',
					'href'               => '/updates',
					'action'             => 'Open',
					'target'             => '',
					'supports_sub_items' => false,
				],
			]
		) )->build();

		$this->assertSame( [ 'malware', 'wp_updates', 'plugin_files' ], \array_column( $query[ 'items' ], 'key' ) );
		$this->assertSame( 'critical', $query[ 'summary' ][ 'severity' ] );
		$this->assertSame( 11, $query[ 'summary' ][ 'total' ] );
	}
}

class BuildAttentionItemsTestDouble extends BuildAttentionItems {

	private array $scanItems;
	private array $maintenanceItems;

	public function __construct( array $scanItems, array $maintenanceItems ) {
		$this->scanItems = $scanItems;
		$this->maintenanceItems = $maintenanceItems;
	}

	protected function buildScanItems() :array {
		return $this->scanItems;
	}

	protected function buildMaintenanceItems() :array {
		return $this->maintenanceItems;
	}
}

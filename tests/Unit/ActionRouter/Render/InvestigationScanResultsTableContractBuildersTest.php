<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	InvestigationFileStatusTableContractBuilder,
	InvestigationMalwareResultsTableContractBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class InvestigationScanResultsTableContractBuildersTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias( static fn( string $value ) :string => \strtolower( \preg_replace( '/[^a-z0-9_]/', '', $value ) ?? '' ) );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testFileStatusBuilderUsesExplicitFullLogHrefAndSharedFlatContractShape() :void {
		$href = '/scans/full-log';
		$table = ( new InvestigationFileStatusTableContractBuilder() )->build(
			'plugin',
			'akismet/akismet.php',
			$href
		);

		$this->assertSame( 'File Scan Status', $table[ 'title' ] ?? '' );
		$this->assertSame( 'file_scan_results', $table[ 'table_type' ] ?? '' );
		$this->assertSame( 'plugin', $table[ 'subject_type' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $table[ 'subject_id' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'show_header' ] ?? true ) );
		$this->assertTrue( (bool)( $table[ 'is_flat' ] ?? false ) );
		$this->assertFalse( (bool)( $table[ 'is_empty' ] ?? true ) );
		$this->assertSame( 'plugin', $table[ 'scan_results_action' ][ 'type' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $table[ 'scan_results_action' ][ 'file' ] ?? '' );
		$this->assertNotSame( '', (string)( $table[ 'table_action' ][ 'ex' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $table[ 'render_item_analysis' ][ 'render_slug' ] ?? '' ) );
	}

	public function testFileStatusBuilderEmptyStatePreservesExplicitFullLogHref() :void {
		$href = '/scans/full-log';
		$table = ( new InvestigationFileStatusTableContractBuilder() )->buildWithEmptyState(
			'core',
			'core',
			0,
			'No issues here.',
			$href
		);

		$this->assertTrue( (bool)( $table[ 'is_empty' ] ?? false ) );
		$this->assertSame( 'No issues here.', $table[ 'empty_text' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertArrayNotHasKey( 'table_type', $table );
		$this->assertArrayNotHasKey( 'datatables_init', $table );
		$this->assertArrayNotHasKey( 'table_action', $table );
		$this->assertArrayNotHasKey( 'subject_type', $table );
	}

	public function testMalwareBuilderUsesExplicitFullLogHrefAndSharedFlatContractShape() :void {
		$href = '/queue/scans';
		$table = ( new InvestigationMalwareResultsTableContractBuilder() )->build( $href );

		$this->assertSame( 'Malware Results', $table[ 'title' ] ?? '' );
		$this->assertSame( 'malware_scan_results', $table[ 'table_type' ] ?? '' );
		$this->assertSame( 'malware', $table[ 'subject_type' ] ?? '' );
		$this->assertSame( 'malware', $table[ 'subject_id' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'show_header' ] ?? true ) );
		$this->assertTrue( (bool)( $table[ 'is_flat' ] ?? false ) );
		$this->assertSame( 'malware', $table[ 'scan_results_action' ][ 'type' ] ?? '' );
		$this->assertSame( 'malware', $table[ 'scan_results_action' ][ 'file' ] ?? '' );
	}
}

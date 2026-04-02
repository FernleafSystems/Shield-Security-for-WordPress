<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsTableContractBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ScanResultsTableContractBuilderTest extends BaseUnitTest {

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
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
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

	public function testFileStatusBuilderUsesDedicatedTableActionContract() :void {
		$href = '/scans/full-log';
		$table = ( new ScanResultsTableContractBuilder() )->buildFileStatus(
			'plugin',
			'akismet/akismet.php',
			$href,
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored' => true,
					'ignored_only'    => true,
				],
			]
		);

		$this->assertSame( 'File Scan Status', $table[ 'title' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'show_header' ] ?? true ) );
		$this->assertTrue( (bool)( $table[ 'is_flat' ] ?? false ) );
		$this->assertFalse( (bool)( $table[ 'is_empty' ] ?? true ) );
		$this->assertNotSame( '', (string)( $table[ 'table_id' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $table[ 'datatables_init_attr' ] ?? '' ) );

		$tableAction = $this->decodeJsonAttr( (string)( $table[ 'table_action_attr' ] ?? '' ) );
		$renderItemAnalysis = $this->decodeJsonAttr( (string)( $table[ 'render_item_analysis_attr' ] ?? '' ) );

		$this->assertSame( 'plugin', $tableAction[ 'type' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $tableAction[ 'file' ] ?? '' );
		$this->assertSame( 'actions_queue', $tableAction[ 'display_context' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored' => true,
				'ignored_only'    => true,
			],
			$tableAction[ 'results_display_options' ] ?? []
		);
		$this->assertNotSame( '', (string)( $tableAction[ 'ex' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $renderItemAnalysis[ 'render_slug' ] ?? '' ) );
	}

	public function testFileStatusBuilderDoesNotInjectExplicitFiltersWithoutInput() :void {
		$table = ( new ScanResultsTableContractBuilder() )->buildFileStatus(
			'plugin',
			'akismet/akismet.php',
			'/queue/scans',
			[
				'display_context' => 'actions_queue',
			]
		);

		$tableAction = $this->decodeJsonAttr( (string)( $table[ 'table_action_attr' ] ?? '' ) );

		$this->assertSame( 'actions_queue', $tableAction[ 'display_context' ] ?? '' );
		$this->assertArrayNotHasKey( 'results_display_options', $tableAction );
	}

	public function testFileStatusBuilderEmptyStatePreservesFullLogHrefAndDropsTableAttrs() :void {
		$href = '/scans/full-log';
		$table = ( new ScanResultsTableContractBuilder() )->buildFileStatusWithEmptyState(
			'core',
			'core',
			0,
			'No issues here.',
			$href
		);

		$this->assertTrue( (bool)( $table[ 'is_empty' ] ?? false ) );
		$this->assertSame( 'No issues here.', $table[ 'empty_text' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertArrayNotHasKey( 'table_id', $table );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $table );
		$this->assertArrayNotHasKey( 'table_action_attr', $table );
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $table );
	}

	public function testMalwareBuilderUsesDedicatedTableActionContract() :void {
		$href = '/queue/scans';
		$table = ( new ScanResultsTableContractBuilder() )->buildMalware( $href );

		$this->assertSame( 'Malware Results', $table[ 'title' ] ?? '' );
		$this->assertSame( $href, $table[ 'full_log_href' ] ?? '' );
		$this->assertFalse( (bool)( $table[ 'show_header' ] ?? true ) );
		$this->assertTrue( (bool)( $table[ 'is_flat' ] ?? false ) );

		$tableAction = $this->decodeJsonAttr( (string)( $table[ 'table_action_attr' ] ?? '' ) );
		$this->assertSame( 'malware', $tableAction[ 'type' ] ?? '' );
		$this->assertSame( 'malware', $tableAction[ 'file' ] ?? '' );
	}

	private function decodeJsonAttr( string $json ) :array {
		return $json === '' ? [] : \json_decode( $json, true, 512, \JSON_THROW_ON_ERROR );
	}
}

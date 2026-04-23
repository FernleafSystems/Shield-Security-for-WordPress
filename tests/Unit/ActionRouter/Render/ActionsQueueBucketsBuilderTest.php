<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueBucketsBuilder,
	ScansResultsRailTabAvailability
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueBucketsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_build_returns_only_critical_and_review_buckets() :void {
		$builder = $this->newBuilder();

		$buckets = $builder->build(
			[
				'items' => [
					[
						'key' => 'malware',
						'label' => 'Malware',
						'count' => 2,
						'severity' => 'critical',
					],
					[
						'key' => 'vulnerable_assets',
						'label' => 'Vulnerabilities',
						'count' => 1,
						'severity' => 'warning',
					],
					[
						'key' => 'wp_updates',
						'label' => 'WordPress Version',
						'count' => 1,
						'severity' => 'warning',
					],
				],
			],
			[
				'scans' => [],
				'maintenance' => [
					[
						'key' => 'system_php_version',
						'label' => 'PHP Version',
						'description' => 'Healthy',
						'drill_bucket' => 'review',
						'item_icon_class' => 'bi bi-code-slash',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_lib_openssl',
						'label' => 'OpenSSL',
						'description' => 'Informational',
						'drill_bucket' => 'review',
						'item_icon_class' => 'bi bi-key-fill',
						'status' => 'neutral',
						'status_label' => 'Neutral',
						'status_icon_class' => 'bi bi-info-circle-fill',
					],
				],
			]
		);
		$bucketsByKey = [];
		foreach ( $buckets as $bucket ) {
			$bucketsByKey[ $bucket[ 'key' ] ] = $bucket;
		}

		$this->assertCount( 2, $buckets );
		$this->assertSame( [ 'critical', 'review' ], \array_keys( $bucketsByKey ) );
		$this->assertSame( 'critical', $bucketsByKey[ 'critical' ][ 'status' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'critical' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame( 2, $bucketsByKey[ 'critical' ][ 'item_count' ] );
		$this->assertSame( 2, $bucketsByKey[ 'critical' ][ 'display_count' ] );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'critical' ][ 'summary_text' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'preview_text', $bucketsByKey[ 'critical' ] );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'summary' ] ?? '' ) );
		$this->assertSame( 'critical', $bucketsByKey[ 'critical' ][ 'selection' ][ 'key' ] );
		$criticalSelectionForJson = $bucketsByKey[ 'critical' ][ 'selection' ];
		unset( $criticalSelectionForJson[ 'selection_json' ] );
		$this->assertSame(
			$criticalSelectionForJson,
			\json_decode( $bucketsByKey[ 'critical' ][ 'selection' ][ 'selection_json' ], true )
		);

		$this->assertSame( 'warning', $bucketsByKey[ 'review' ][ 'status' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'review' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame( 2, $bucketsByKey[ 'review' ][ 'item_count' ] );
		$this->assertSame( 2, $bucketsByKey[ 'review' ][ 'display_count' ] );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'review' ][ 'summary_text' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'preview_text', $bucketsByKey[ 'review' ] );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'review' ][ 'selection' ][ 'header' ][ 'summary' ] ?? '' ) );
		$this->assertSame( 'review', $bucketsByKey[ 'review' ][ 'selection' ][ 'key' ] );
	}

	public function test_build_keeps_healthy_only_bucket_interactive_and_good() :void {
		$builder = $this->newBuilder();

		$buckets = $builder->build(
			[
				'items' => [],
			],
			[
				'scans' => [
					[
						'key' => 'vulnerable_assets',
						'label' => 'Known Vulnerabilities',
						'description' => 'Previous scans did not detect any vulnerable assets.',
						'drill_bucket' => 'critical',
						'item_icon_class' => 'bi bi-shield-exclamation',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [
					[
						'key' => 'system_php_version',
						'label' => 'PHP Version',
						'description' => 'Healthy.',
						'drill_bucket' => 'review',
						'item_icon_class' => 'bi bi-code-slash',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);
		$bucketsByKey = [];
		foreach ( $buckets as $bucket ) {
			$bucketsByKey[ $bucket[ 'key' ] ] = $bucket;
		}

		$this->assertSame( 'good', $bucketsByKey[ 'critical' ][ 'status' ] );
		$this->assertSame( 0, $bucketsByKey[ 'critical' ][ 'item_count' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'critical' ][ 'is_interactive' ] ?? false ) );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'critical' ][ 'summary_text' ] ?? '' ) );
		$this->assertSame(
			$bucketsByKey[ 'critical' ][ 'summary_text' ],
			$bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'good', $bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'good', $bucketsByKey[ 'review' ][ 'status' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'review' ][ 'is_interactive' ] ?? false ) );
	}

	public function test_build_prefers_good_bucket_state_over_disabled_lane_copy_when_healthy_rows_exist() :void {
		$builder = $this->newBuilder( [
			'plugins' => [
				'is_available'     => false,
				'disabled_reason'  => 'upgrade_required',
				'disabled_message' => 'Plugin file scanning requires an upgrade.',
			],
			'malware' => [
				'is_available'     => false,
				'disabled_reason'  => 'upgrade_required',
				'disabled_message' => 'Malware scanning requires an upgrade.',
			],
		] );

		$buckets = $builder->build(
			[
				'items' => [],
			],
			[
				'scans' => [
					[
						'key' => 'wp_files',
						'label' => 'WordPress Files',
						'description' => 'WordPress core files are healthy.',
						'drill_bucket' => 'critical',
						'item_icon_class' => 'bi bi-wordpress',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			]
		);
		$bucketsByKey = [];
		foreach ( $buckets as $bucket ) {
			$bucketsByKey[ $bucket[ 'key' ] ] = $bucket;
		}

		$this->assertSame( 'good', $bucketsByKey[ 'critical' ][ 'status' ] );
		$this->assertSame( 0, $bucketsByKey[ 'critical' ][ 'display_count' ] );
		$this->assertNotSame( '', (string)( $bucketsByKey[ 'critical' ][ 'summary_text' ] ?? '' ) );
		$this->assertSame(
			$bucketsByKey[ 'critical' ][ 'summary_text' ],
			$bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'good', $bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'badge_status' ] ?? '' );
	}

	public function test_build_healthy_rows_collects_only_good_assessment_rows() :void {
		$builder = $this->newBuilder();

		$healthyRows = $builder->buildHealthyRows(
			[
				'scans' => [
					[
						'key' => 'malware',
						'label' => 'Malware Scan',
						'description' => 'No malware found.',
						'drill_bucket' => 'critical',
						'item_icon_class' => 'bi bi-bug-fill',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-shield-check',
					],
					[
						'key' => 'themes',
						'label' => 'Themes',
						'description' => 'Needs review.',
						'drill_bucket' => 'critical',
						'item_icon_class' => 'bi bi-palette-fill',
						'status' => 'warning',
						'status_label' => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
				],
				'maintenance' => [
					[
						'key' => 'system_php_version',
						'label' => 'PHP Version',
						'description' => 'Healthy.',
						'drill_bucket' => 'review',
						'item_icon_class' => 'bi bi-code-slash',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_ssl_certificate',
						'label' => 'SSL Certificate',
						'description' => 'Informational.',
						'drill_bucket' => 'review',
						'item_icon_class' => 'bi bi-shield-lock-fill',
						'status' => 'neutral',
						'status_label' => 'Neutral',
						'status_icon_class' => 'bi bi-info-circle-fill',
					],
				],
			]
		);

		$this->assertSame(
			[
				[
					'icon_class'  => 'bi bi-bug-fill',
					'title'       => 'Malware Scan',
					'inline_meta' => '',
					'summary'     => 'No malware found.',
					'badge_label' => '',
					'is_ignored'  => false,
					'actions'     => [],
				],
				[
					'icon_class'  => 'bi bi-code-slash',
					'title'       => 'PHP Version',
					'inline_meta' => '',
					'summary'     => 'Healthy.',
					'badge_label' => '',
					'is_ignored'  => false,
					'actions'     => [],
				],
			],
			$healthyRows
		);
	}

	public function test_classify_skips_attention_items_without_supported_bucket_sources() :void {
		$builder = $this->newBuilder();

		$classified = $builder->classify(
			[
				'items' => [
					[
						'key' => 'plugin_files',
						'label' => 'Plugin Files',
						'count' => 3,
						'severity' => 'warning',
					],
					[
						'key' => 'wp_updates',
						'label' => 'WordPress Version',
						'count' => 8,
						'severity' => 'good',
					],
				],
			]
		);

		$this->assertSame( [ 'critical', 'review' ], \array_keys( $classified ) );
		$this->assertSame( 3, $classified[ 'review' ][ 'item_count' ] );
		$this->assertSame( 0, $classified[ 'critical' ][ 'item_count' ] );
	}

	public function test_classify_routes_ignored_only_scan_attention_items_to_fix_now() :void {
		$classified = $this->newBuilder()->classify(
			[
				'items' => [
					[
						'key'      => 'wp_files_ignored',
						'label'    => 'ignored-wp-label',
						'count'    => 2,
						'severity' => 'warning',
					],
					[
						'key'      => 'plugin_files_ignored',
						'label'    => 'ignored-plugin-label',
						'count'    => 2,
						'severity' => 'warning',
					],
					[
						'key'      => 'theme_files_ignored',
						'label'    => 'ignored-theme-label',
						'count'    => 1,
						'severity' => 'warning',
					],
					[
						'key'      => 'malware_ignored',
						'label'    => 'ignored-malware-label',
						'count'    => 3,
						'severity' => 'warning',
					],
				],
			]
		);

		$this->assertSame( 8, $classified[ 'critical' ][ 'item_count' ] );
		$this->assertCount( 4, $classified[ 'critical' ][ 'attention_items' ] );
		$this->assertSame( 0, $classified[ 'review' ][ 'item_count' ] );
	}

	private function newBuilder( array $availability = [] ) :ActionsQueueBucketsBuilder {
		return new ActionsQueueBucketsBuilder(
			new class( $availability ) extends ScansResultsRailTabAvailability {
				public function __construct( private array $availability ) {
				}

				public function build( string $tabKey ) :array {
					return \array_merge( [
						'is_available'          => true,
						'show_in_actions_queue' => true,
						'show_in_fix_now'       => true,
						'disabled_reason'       => '',
						'disabled_message'      => '',
						'disabled_status'       => '',
						'disabled_actions'      => [],
					], $this->availability[ $tabKey ] ?? [] );
				}
			}
		);
	}
}

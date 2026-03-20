<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueBucketsBuilder;
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
		$builder = new ActionsQueueBucketsBuilder();

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
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_lib_openssl',
						'label' => 'OpenSSL',
						'description' => 'Informational',
						'drill_bucket' => 'review',
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
		$this->assertSame( 'Fix now', $bucketsByKey[ 'critical' ][ 'label' ] );
		$this->assertSame( 'Critical', $bucketsByKey[ 'critical' ][ 'state_label' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'critical' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame( 2, $bucketsByKey[ 'critical' ][ 'item_count' ] );
		$this->assertSame( '2 malware detections', $bucketsByKey[ 'critical' ][ 'summary_text' ] );
		$this->assertArrayNotHasKey( 'preview_text', $bucketsByKey[ 'critical' ] );
		$this->assertSame(
			'Fix now contains 2 items that still need attention.',
			$bucketsByKey[ 'critical' ][ 'header' ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'critical', $bucketsByKey[ 'critical' ][ 'selection' ][ 'key' ] );
		$this->assertSame( 'Back to Actions Queue', $bucketsByKey[ 'critical' ][ 'selection' ][ 'header' ][ 'active_back_label' ] ?? '' );
		$criticalSelectionForJson = $bucketsByKey[ 'critical' ][ 'selection' ];
		unset( $criticalSelectionForJson[ 'selection_json' ] );
		$this->assertSame(
			$criticalSelectionForJson,
			\json_decode( $bucketsByKey[ 'critical' ][ 'selection_json' ], true )
		);

		$this->assertSame( 'warning', $bucketsByKey[ 'review' ][ 'status' ] );
		$this->assertSame( 'Review', $bucketsByKey[ 'review' ][ 'state_label' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'review' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame( 2, $bucketsByKey[ 'review' ][ 'item_count' ] );
		$this->assertSame( '1 vulnerability, 1 maintenance item', $bucketsByKey[ 'review' ][ 'summary_text' ] );
		$this->assertArrayNotHasKey( 'preview_text', $bucketsByKey[ 'review' ] );
		$this->assertSame( 'Review queue', $bucketsByKey[ 'review' ][ 'header' ][ 'meta' ] ?? '' );
	}

	public function test_build_keeps_healthy_only_bucket_interactive_and_good() :void {
		$builder = new ActionsQueueBucketsBuilder();

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
		$this->assertSame( 'Looking good', $bucketsByKey[ 'critical' ][ 'state_label' ] );
		$this->assertSame( 0, $bucketsByKey[ 'critical' ][ 'item_count' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'critical' ][ 'is_interactive' ] ?? false ) );
		$this->assertSame(
			'Everything in this bucket is currently looking good.',
			$bucketsByKey[ 'critical' ][ 'summary_text' ]
		);
		$this->assertSame( 'Critical queue', $bucketsByKey[ 'critical' ][ 'header' ][ 'meta' ] ?? '' );
		$this->assertSame(
			'Everything in this bucket is currently looking good.',
			$bucketsByKey[ 'critical' ][ 'header' ][ 'summary' ] ?? ''
		);
		$this->assertSame( 'good', $bucketsByKey[ 'review' ][ 'status' ] );
		$this->assertTrue( (bool)( $bucketsByKey[ 'review' ][ 'is_interactive' ] ?? false ) );
	}

	public function test_build_healthy_disclosure_collects_only_good_assessment_rows() :void {
		$builder = new ActionsQueueBucketsBuilder();

		$healthyDisclosure = $builder->buildHealthyDisclosure(
			[
				'scans' => [
					[
						'key' => 'malware',
						'label' => 'Malware Scan',
						'description' => 'No malware found.',
						'drill_bucket' => 'critical',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-shield-check',
					],
					[
						'key' => 'themes',
						'label' => 'Themes',
						'description' => 'Needs review.',
						'drill_bucket' => 'critical',
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
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_ssl_certificate',
						'label' => 'SSL Certificate',
						'description' => 'Informational.',
						'drill_bucket' => 'review',
						'status' => 'neutral',
						'status_label' => 'Neutral',
						'status_icon_class' => 'bi bi-info-circle-fill',
					],
				],
			]
		);

		$this->assertSame( 'No action required', $healthyDisclosure[ 'label' ] );
		$this->assertSame(
			[
				[
					'icon_class' => 'bi bi-shield-check',
					'title'      => 'Malware Scan',
					'summary'    => 'No malware found.',
				],
				[
					'icon_class' => 'bi bi-check-circle-fill',
					'title'      => 'PHP Version',
					'summary'    => 'Healthy.',
				],
			],
			$healthyDisclosure[ 'items' ]
		);
	}

	public function test_classify_skips_attention_items_without_supported_bucket_sources() :void {
		$builder = new ActionsQueueBucketsBuilder();

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
}

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

	public function test_build_classifies_attention_items_and_later_maintenance_rows() :void {
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
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_lib_openssl',
						'label' => 'OpenSSL',
						'description' => 'Informational',
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

		$this->assertSame( 'Fix now', $bucketsByKey[ 'critical' ][ 'label' ] );
		$this->assertSame( 2, $bucketsByKey[ 'critical' ][ 'item_count' ] );
		$this->assertSame( '2 malware detections', $bucketsByKey[ 'critical' ][ 'summary_text' ] );
		$this->assertSame( 'Malware', $bucketsByKey[ 'critical' ][ 'preview_text' ] );
		$this->assertSame( 'Fix now - 2 items', $bucketsByKey[ 'critical' ][ 'strip_text' ] );
		$this->assertSame( '2 items', $bucketsByKey[ 'critical' ][ 'strip_badge' ] );
		$this->assertSame(
			'{"path":["Triage buckets","Fix now"],"focus":"Fix now contains 2 items that still need attention.","next_step":"Choose a group to review the matching results."}',
			$bucketsByKey[ 'critical' ][ 'context_json' ]
		);
		$this->assertSame( 'critical', $bucketsByKey[ 'critical' ][ 'selection' ][ 'key' ] );
		$this->assertSame(
			'{"path":["Triage buckets","Fix now"],"focus":"Fix now contains 2 items that still need attention.","next_step":"Choose a group to review the matching results."}',
			$bucketsByKey[ 'critical' ][ 'selection' ][ 'context_json' ]
		);
		$criticalSelectionForJson = $bucketsByKey[ 'critical' ][ 'selection' ];
		unset( $criticalSelectionForJson[ 'selection_json' ] );
		$this->assertSame(
			$criticalSelectionForJson,
			\json_decode( $bucketsByKey[ 'critical' ][ 'selection_json' ], true )
		);

		$this->assertSame( 'warning', $bucketsByKey[ 'review' ][ 'status' ] );
		$this->assertSame( 2, $bucketsByKey[ 'review' ][ 'item_count' ] );
		$this->assertSame( '1 vulnerability, 1 maintenance item', $bucketsByKey[ 'review' ][ 'summary_text' ] );
		$this->assertSame(
			[
				'path'      => [ 'Triage buckets', 'Review next' ],
				'focus'     => 'Review next contains 2 items that still need attention.',
				'next_step' => 'Choose a group to review the matching results.',
			],
			$bucketsByKey[ 'review' ][ 'context' ]
		);

		$this->assertSame( 'good', $bucketsByKey[ 'later' ][ 'status' ] );
		$this->assertSame( 2, $bucketsByKey[ 'later' ][ 'item_count' ] );
		$this->assertSame( '2 maintenance checks', $bucketsByKey[ 'later' ][ 'summary_text' ] );
		$this->assertSame( 'PHP Version', $bucketsByKey[ 'later' ][ 'preview_text' ] );
		$this->assertSame( $bucketsByKey[ 'later' ][ 'strip_text' ], $bucketsByKey[ 'later' ][ 'selection' ][ 'strip_text' ] );
		$this->assertSame( $bucketsByKey[ 'later' ][ 'selection_json' ], $bucketsByKey[ 'later' ][ 'selection' ][ 'selection_json' ] );
	}

	public function test_classify_keeps_good_and_neutral_maintenance_rows_in_later_only() :void {
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
				],
			],
			[
				'scans' => [],
				'maintenance' => [
					[
						'key' => 'system_php_version',
						'label' => 'PHP Version',
						'description' => 'Healthy',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_ssl_certificate',
						'label' => 'SSL Certificate',
						'description' => 'Needs review',
						'status' => 'warning',
						'status_label' => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
				],
			]
		);

		$this->assertCount( 0, $classified[ 'critical' ][ 'maintenance_rows' ] );
		$this->assertCount( 0, $classified[ 'review' ][ 'maintenance_rows' ] );
		$this->assertCount( 1, $classified[ 'later' ][ 'maintenance_rows' ] );
		$this->assertSame( 3, $classified[ 'review' ][ 'item_count' ] );
		$this->assertSame( 1, $classified[ 'later' ][ 'item_count' ] );
	}
}

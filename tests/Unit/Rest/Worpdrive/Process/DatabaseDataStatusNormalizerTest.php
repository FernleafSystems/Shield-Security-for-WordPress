<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process\DatabaseDataStatusNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class DatabaseDataStatusNormalizerTest extends BaseUnitTest {

	public function test_normalize_repairs_malformed_href_and_table_map_without_touching_diagnostics() :void {
		$diagnostics = [
			'query_count' => 4,
			'timing'      => [
				'db_ms' => 12,
			],
		];

		$status = ( new DatabaseDataStatusNormalizer() )->normalize( [
			'href'             => [ 'not-a-url' ],
			'table_export_map' => 'not-a-map',
			'diagnostics'      => $diagnostics,
		] );

		$this->assertSame( '', $status[ 'href' ] );
		$this->assertSame( [], $status[ 'table_export_map' ] );
		$this->assertSame( $diagnostics, $status[ 'diagnostics' ] );
	}

	public function test_normalize_promotes_valid_context_map_when_top_level_map_is_empty_and_preserves_cursor() :void {
		$contextMap = [
			'wp_options' => $this->tableStatus( [
				'cursor' => [
					'strategy' => 'unique_index',
					'index'    => 'option_name',
					'columns'  => [ 'option_name' ],
					'values'   => [ 'siteurl' ],
				],
			] ),
		];

		$status = ( new DatabaseDataStatusNormalizer() )->normalize( [
			'href'             => '',
			'table_export_map' => [],
			'error_context'    => [
				'table_export_map' => $contextMap,
			],
		] );

		$this->assertSame( $contextMap, $status[ 'table_export_map' ] );
		$this->assertSame( $contextMap, $status[ 'error_context' ][ 'table_export_map' ] );
	}

	public function test_normalize_preserves_non_empty_top_level_map_precedence() :void {
		$topLevelMap = [
			'wp_options' => $this->tableStatus( [ 'offset' => 3 ] ),
		];
		$contextMap = [
			'wp_posts' => $this->tableStatus(),
		];

		$status = ( new DatabaseDataStatusNormalizer() )->normalize( [
			'href'             => '',
			'table_export_map' => $topLevelMap,
			'error_context'    => [
				'table_export_map' => $contextMap,
			],
		] );

		$this->assertSame( $topLevelMap, $status[ 'table_export_map' ] );
		$this->assertSame( $contextMap, $status[ 'error_context' ][ 'table_export_map' ] );
	}

	public function test_normalize_does_not_promote_invalid_context_map() :void {
		$invalidMap = [
			'wp_options' => [
				'offset'        => 0,
				'page'          => 0,
				'completed_at'  => 0,
				'exported_rows' => 0,
				'max_page_rows' => 10,
			],
		];

		$status = ( new DatabaseDataStatusNormalizer() )->normalize( [
			'href'          => '',
			'error_context' => [
				'table_export_map' => $invalidMap,
			],
		] );

		$this->assertSame( [], $status[ 'table_export_map' ] );
		$this->assertSame( $invalidMap, $status[ 'error_context' ][ 'table_export_map' ] );
	}

	private function tableStatus( array $overrides = [] ) :array {
		return \array_merge( [
			'offset'        => 0,
			'page'          => 0,
			'completed_at'  => 0,
			'exported_rows' => 0,
			'max_page_rows' => 10,
			'chunk_size'    => 2,
		], $overrides );
	}
}

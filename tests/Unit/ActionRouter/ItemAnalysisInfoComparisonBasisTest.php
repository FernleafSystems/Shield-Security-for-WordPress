<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\Info;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class ItemAnalysisInfoComparisonBasisTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_datamanipulation' => new DataManipulation(),
			'service_corefilehashes' => new class extends CoreFileHashes {
				public function isCoreFile( $file ) :bool {
					unset( $file );
					return false;
				}
			},
			'service_wpgeneral' => new class extends General {
				public function getVersion( $ignoreClassicpress = false ) :string {
					unset( $ignoreClassicpress );
					return '6.0.0';
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_published_plugin_checksum_description_retains_published_reference_meaning() :void {
		$description = \implode( "\n", $this->descriptionLinesFor( $this->pluginItem( [
			'is_checksumfail'  => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE,
		] ) ) );

		$this->assertStringContainsString( 'official', $description );
		$this->assertStringNotContainsString( 'baseline', $description );
	}

	public function test_local_theme_unrecognised_description_uses_shield_baseline_meaning() :void {
		$description = \implode( "\n", $this->descriptionLinesFor( $this->themeItem( [
			'is_unrecognised' => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
		] ) ) );

		$this->assertStringContainsString( 'appeared', $description );
		$this->assertStringContainsString( 'Shield', $description );
		$this->assertStringContainsString( 'baseline', $description );
		$this->assertStringNotContainsString( 'official', $description );
	}

	public function test_local_plugin_checksum_description_uses_shield_baseline_meaning() :void {
		$description = \implode( "\n", $this->descriptionLinesFor( $this->pluginItem( [
			'is_checksumfail'  => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
		] ) ) );

		$this->assertStringContainsString( 'changed', $description );
		$this->assertStringContainsString( 'Shield', $description );
		$this->assertStringContainsString( 'baseline', $description );
		$this->assertStringNotContainsString( 'official', $description );
	}

	/**
	 * @dataProvider providerLegacyComparisonBasis
	 */
	public function test_legacy_plugin_checksum_description_is_neutral_about_provenance( ?string $comparisonBasis ) :void {
		$properties = [ 'is_checksumfail' => true ];
		if ( $comparisonBasis !== null ) {
			$properties[ 'comparison_basis' ] = $comparisonBasis;
		}
		$descriptionLines = $this->descriptionLinesFor( $this->pluginItem( $properties ) );
		$description = \implode( "\n", $descriptionLines );

		$this->assertCount( 2, $descriptionLines );
		$this->assertStringNotContainsString( 'official', $description );
		$this->assertStringNotContainsString( 'baseline', $description );
	}

	public static function providerLegacyComparisonBasis() :array {
		return [
			'missing' => [ null ],
			'invalid' => [ 'unknown' ],
		];
	}

	public function test_core_checksum_description_retains_official_semantics() :void {
		$description = \implode( "\n", $this->descriptionLinesFor( $this->item( [
			'is_in_core'      => true,
			'is_checksumfail' => true,
		] ) ) );

		$this->assertStringContainsString( 'official', $description );
		$this->assertStringNotContainsString( 'baseline', $description );
	}

	public function test_malware_only_plugin_description_has_neutral_ownership() :void {
		$description = \implode( "\n", $this->descriptionLinesFor( $this->pluginItem( [
			'is_mal' => true,
		] ) ) );

		$this->assertStringContainsString( 'plugin directory', $description );
		$this->assertStringNotContainsString( 'official', $description );
		$this->assertStringNotContainsString( 'valid file', $description );
		$this->assertStringNotContainsString( 'clean', $description );
		$this->assertStringNotContainsString( 'baseline', $description );
	}

	private function descriptionLinesFor( ResultItem $item ) :array {
		return ( new ItemAnalysisInfoComparisonBasisTestDouble( [
			'scan_item' => $item,
		] ) )->renderDataForTest()[ 'vars' ][ 'file_description' ];
	}

	private function pluginItem( array $properties ) :ResultItem {
		return $this->item( [ 'is_in_plugin' => true ] + $properties );
	}

	private function themeItem( array $properties ) :ResultItem {
		return $this->item( [ 'is_in_theme' => true ] + $properties );
	}

	private function item( array $properties ) :ResultItem {
		$item = new ResultItem();
		$item->path_fragment = 'wp-content/plugins/example/example.php';
		$item->path_full = 'C:/tmp/example.php';
		foreach ( $properties as $key => $value ) {
			$item->{$key} = $value;
		}
		return $item;
	}
}

class ItemAnalysisInfoComparisonBasisTestDouble extends Info {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}

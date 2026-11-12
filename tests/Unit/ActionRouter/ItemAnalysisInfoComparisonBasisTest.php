<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\{
	Info,
	OriginalFileSource
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\MalwareAssessmentPresenter;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
use FernleafSystems\Wordpress\Services\Core\Fs;
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class ItemAnalysisInfoComparisonBasisTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class {
			public function canScanMalwareMalai() :bool {
				return false;
			}
		};
		$controller->plugin_urls = new class {
			public function fileDownload( string $category, array $params ) :string {
				return '/download/'.$category.'/'.$params[ 'rid' ];
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'icon-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_datamanipulation' => new DataManipulation(),
			'service_wpfs' => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					return $path === 'C:/tmp/example.php';
				}
			},
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
		ItemAnalysisInfoComparisonBasisTestDouble::$source = null;
		PluginControllerInstaller::reset();
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'providerLegacyComparisonBasis' )]
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

	public function test_info_uses_shared_assessment_and_keeps_independent_finding_description() :void {
		$record = new Record();
		$record->malai_status = 'predicted_clean';
		$record->malai_status_context = '';
		$item = $this->pluginItem( [
			'is_mal' => true,
			'is_checksumfail' => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE,
		] )->setMalwareRecord( $record );
		$data = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $item ] ) )->renderDataForTest();
		$assessment = ( new MalwareAssessmentPresenter() )->present( $record, false );
		$this->assertSame( [ $assessment[ 'explanation' ], $assessment[ 'guidance' ] ], $data[ 'vars' ][ 'verdict_why' ] );
		$this->assertCount( 2, $data[ 'vars' ][ 'file_description' ] );
		$item->is_mal = false;
		$withoutMalware = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $item ] ) )->renderDataForTest();
		$this->assertSame( $withoutMalware[ 'vars' ][ 'file_description' ], $data[ 'vars' ][ 'file_description' ] );
		$this->assertSame( [], $withoutMalware[ 'vars' ][ 'verdict_why' ] );
	}

	public function test_a_settled_outcome_is_all_in_its_headline_and_an_unsettled_one_says_where_it_stands() :void {
		$settled = new Record();
		$settled->malai_status = 'malware';
		$settled->malai_status_context = '';
		$this->assertSame( '', ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_mal' => true,
		] )->setMalwareRecord( $settled ) ] ) )->renderDataForTest()[ 'vars' ][ 'verdict_note' ] );

		$unsettled = new Record();
		$unsettled->malai_status = '';
		$unsettled->malai_status_context = 'inconclusive';
		$this->assertSame(
			( new MalwareAssessmentPresenter() )->present( $unsettled, false )[ 'summary' ],
			( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
				'is_mal' => true,
			] )->setMalwareRecord( $unsettled ) ] ) )->renderDataForTest()[ 'vars' ][ 'verdict_note' ]
		);
	}

	public function test_the_file_can_be_downloaded_from_the_overview_only_when_it_is_accessible() :void {
		$data = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_checksumfail' => true,
		] ) ] ) )->renderDataForTest();
		$this->assertTrue( $data[ 'flags' ][ 'can_download' ] );
		$this->assertSame( '/download/scan_file/7', $data[ 'hrefs' ][ 'file_download' ] );

		$this->assertFalse( ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_checksumfail' => true,
			'path_full'       => 'C:/tmp/gone.php',
		] ) ] ) )->renderDataForTest()[ 'flags' ][ 'can_download' ] );
	}

	public function test_overview_leads_with_the_malware_verdict_and_lists_every_status() :void {
		$data = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_mal'          => true,
			'is_checksumfail' => true,
		] ) ] ) )->renderDataForTest();

		$this->assertSame( 'danger', $data[ 'vars' ][ 'verdict_tone' ] );
		$this->assertStringContainsString( 'malware', $data[ 'vars' ][ 'verdict_title' ] );
		$this->assertSame( [
			[ 'label' => 'Malware pattern match', 'tone' => 'danger' ],
			[ 'label' => 'Modified', 'tone' => 'warning' ],
		], $data[ 'vars' ][ 'file_statuses' ] );
	}

	public function test_overview_does_not_call_a_likely_clean_pattern_match_malware() :void {
		$record = new Record();
		$record->malai_status = 'predicted_clean';
		$record->malai_status_context = '';

		$malwareOnly = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_mal' => true,
		] )->setMalwareRecord( $record ) ] ) )->renderDataForTest();
		$this->assertSame( 'warning', $malwareOnly[ 'vars' ][ 'verdict_tone' ] );
		$this->assertStringContainsString( 'likely clean', $malwareOnly[ 'vars' ][ 'verdict_title' ] );
		$this->assertSame( [
			[ 'label' => 'Pattern match: likely clean', 'tone' => 'secondary' ],
		], $malwareOnly[ 'vars' ][ 'file_statuses' ] );

		$alsoModified = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->pluginItem( [
			'is_mal'          => true,
			'is_checksumfail' => true,
		] )->setMalwareRecord( $record ) ] ) )->renderDataForTest();
		$this->assertSame( 'warning', $alsoModified[ 'vars' ][ 'verdict_tone' ] );
		$this->assertStringContainsString( 'modified', $alsoModified[ 'vars' ][ 'verdict_title' ] );
		$this->assertStringContainsString( 'likely clean', $alsoModified[ 'vars' ][ 'verdict_note' ] );
	}

	public function test_overview_without_malware_uses_the_finding_as_its_verdict() :void {
		$data = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $this->themeItem( [
			'is_unrecognised' => true,
		] ) ] ) )->renderDataForTest();

		$this->assertSame( 'warning', $data[ 'vars' ][ 'verdict_tone' ] );
		$this->assertStringContainsString( 'recognised', $data[ 'vars' ][ 'verdict_title' ] );
		$this->assertSame( [ [ 'label' => 'Unrecognised', 'tone' => 'warning' ] ], $data[ 'vars' ][ 'file_statuses' ] );
	}

	public function test_overview_names_the_owning_asset_only_when_there_is_one() :void {
		$item = $this->pluginItem( [ 'is_checksumfail' => true ] );
		ItemAnalysisInfoComparisonBasisTestDouble::$source = new OriginalFileSource(
			OriginalFileSource::KIND_PREMIUM_PLUGIN, 'Easy Digital Downloads Pro', '3.7.2'
		);
		$premium = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $item ] ) )->renderDataForTest();
		ItemAnalysisInfoComparisonBasisTestDouble::$source = null;
		$unowned = ( new ItemAnalysisInfoComparisonBasisTestDouble( [ 'scan_item' => $item ] ) )->renderDataForTest();

		$this->assertStringContainsString( 'Easy Digital Downloads Pro 3.7.2', $premium[ 'vars' ][ 'belongs_to' ] );
		$this->assertStringContainsString( 'premium', $premium[ 'vars' ][ 'belongs_to' ] );
		$this->assertSame( '', $unowned[ 'vars' ][ 'belongs_to' ] );
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
		$item->VO = (object)[ 'resultitem_id' => 7 ];
		foreach ( $properties as $key => $value ) {
			$item->{$key} = $value;
		}
		return $item;
	}
}

class ItemAnalysisInfoComparisonBasisTestDouble extends Info {

	public static ?OriginalFileSource $source = null;

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function resolveSource() :OriginalFileSource {
		return self::$source ?? new OriginalFileSource( OriginalFileSource::KIND_NONE, '', '' );
	}
}

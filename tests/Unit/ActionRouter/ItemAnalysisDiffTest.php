<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis\{
	Diff,
	OriginalFileSource
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class ItemAnalysisDiffTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpfs' => new class extends Fs {
				public function isAccessibleFile( string $path ) :bool {
					return true;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_missing_file_makes_no_network_request() :void {
		$diff = $this->diff( [ 'is_missing' => true ], $this->wpOrgPlugin() );

		$this->assertMessage( $diff->renderDataForTest(), 'neutral', 'no longer exists', false );
		$this->assertSame( [], $diff->networkCalls );
	}

	public function test_file_outside_core_plugins_and_themes_has_no_official_copy() :void {
		$diff = $this->diff( [ 'is_unidentified' => true ], new OriginalFileSource( OriginalFileSource::KIND_NONE, '', '' ) );

		$this->assertMessage( $diff->renderDataForTest(), 'neutral', 'No official copy', false );
		$this->assertSame( [], $diff->networkCalls );
	}

	public function test_premium_plugin_is_a_normal_state_without_any_network_request() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], new OriginalFileSource(
			OriginalFileSource::KIND_PREMIUM_PLUGIN, 'Easy Digital Downloads Pro', '3.7.2'
		) );

		$data = $diff->renderDataForTest();

		$this->assertMessage( $data, 'neutral', 'No official copy', false );
		$this->assertStringContainsString( 'Easy Digital Downloads Pro', \implode( ' ', $data[ 'vars' ][ 'lines' ] ) );
		$this->assertStringContainsString( 'premium', \implode( ' ', $data[ 'vars' ][ 'lines' ] ) );
		$this->assertSame( [], $diff->networkCalls );
	}

	public function test_theme_not_hosted_on_wordpress_org_is_premium() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], $this->theme() );
		$diff->themeOnWpOrg = false;

		$data = $diff->renderDataForTest();

		$this->assertMessage( $data, 'neutral', 'No official copy', false );
		$this->assertStringContainsString( 'The Divi theme', \implode( ' ', $data[ 'vars' ][ 'lines' ] ) );
		$this->assertSame( [ 'theme' ], $diff->networkCalls );
	}

	public function test_theme_hosted_on_wordpress_org_is_compared() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], $this->theme() );

		$this->assertTrue( $diff->renderDataForTest()[ 'flags' ][ 'has_diff' ] );
		$this->assertSame( [ 'theme', 'fetch', 'diff' ], $diff->networkCalls );
	}

	public function test_file_missing_from_published_hashes_is_not_in_the_release() :void {
		$diff = $this->diff( [
			'is_unrecognised'  => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE,
		], $this->wpOrgPlugin() );

		$data = $diff->renderDataForTest();

		$this->assertMessage( $data, 'neutral', "isn't part of the official release", false );
		$this->assertStringContainsString( 'Akismet 5.3', \implode( ' ', $data[ 'vars' ][ 'lines' ] ) );
		$this->assertSame( [], $diff->networkCalls );
	}

	public function test_unrecognised_core_file_is_not_in_the_release() :void {
		$diff = $this->diff( [ 'is_unrecognised' => true ], new OriginalFileSource( OriginalFileSource::KIND_CORE, 'WordPress', '6.8.1' ) );

		$this->assertMessage( $diff->renderDataForTest(), 'neutral', "isn't part of the official release", false );
	}

	public function test_file_missing_only_from_shields_baseline_is_still_compared() :void {
		$diff = $this->diff( [
			'is_unrecognised'  => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
		], $this->wpOrgPlugin() );

		$this->assertTrue( $diff->renderDataForTest()[ 'flags' ][ 'has_diff' ] );
	}

	public function test_failed_download_offers_a_retry() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], $this->wpOrgPlugin() );
		$diff->original = null;

		$data = $diff->renderDataForTest();

		$this->assertMessage( $data, 'warning', "couldn't fetch the original file", true );
		$this->assertStringContainsString( 'try again in a few minutes', \implode( ' ', $data[ 'vars' ][ 'lines' ] ) );
	}

	public function test_failed_comparison_offers_a_retry() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], $this->wpOrgPlugin() );
		$diff->diffResult = null;

		$this->assertMessage( $diff->renderDataForTest(), 'warning', "couldn't create the diff", true );
	}

	public function test_successful_diff_states_what_it_was_compared_with() :void {
		$diff = $this->diff( [ 'is_checksumfail' => true ], $this->wpOrgPlugin() );

		$data = $diff->renderDataForTest();

		$this->assertTrue( $data[ 'flags' ][ 'has_diff' ] );
		$this->assertFalse( $data[ 'flags' ][ 'can_retry' ] );
		$this->assertStringContainsString( 'Akismet 5.3', $data[ 'vars' ][ 'legend' ] );
		$this->assertSame( '<table class="diff"></table>', $data[ 'vars' ][ 'diff_content' ] );
		$this->assertSame( [ 'fetch', 'diff' ], $diff->networkCalls );
	}

	private function assertMessage( array $data, string $tone, string $titleFragment, bool $canRetry ) :void {
		$this->assertFalse( $data[ 'flags' ][ 'has_diff' ] );
		$this->assertSame( $canRetry, $data[ 'flags' ][ 'can_retry' ] );
		$this->assertSame( $tone, $data[ 'vars' ][ 'tone' ] );
		$this->assertStringContainsString( $titleFragment, $data[ 'vars' ][ 'title' ] );
		$this->assertNotEmpty( $data[ 'vars' ][ 'lines' ] );
	}

	private function wpOrgPlugin() :OriginalFileSource {
		return new OriginalFileSource( OriginalFileSource::KIND_WPORG_PLUGIN, 'Akismet', '5.3' );
	}

	private function theme() :OriginalFileSource {
		return new OriginalFileSource( OriginalFileSource::KIND_THEME, 'Divi', '4.27.4' );
	}

	private function diff( array $properties, OriginalFileSource $source ) :ItemAnalysisDiffTestDouble {
		$item = new ResultItem();
		$item->path_fragment = 'wp-content/plugins/example/example.php';
		$item->path_full = 'C:/tmp/example.php';
		foreach ( $properties as $key => $value ) {
			$item->{$key} = $value;
		}
		return new ItemAnalysisDiffTestDouble( $item, $source );
	}
}

class ItemAnalysisDiffTestDouble extends Diff {

	/** @var list<string> */
	public array $networkCalls = [];

	public bool $themeOnWpOrg = true;

	public ?string $original = 'original';

	public ?array $diffResult = [ 'css' => '', 'content' => '<table class="diff"></table>' ];

	private ResultItem $item;

	private OriginalFileSource $source;

	public function __construct( ResultItem $item, OriginalFileSource $source ) {
		parent::__construct( [ 'rid' => 1 ] );
		$this->item = $item;
		$this->source = $source;
	}

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function loadFileScanItem() :ResultItem {
		return $this->item;
	}

	protected function resolveSource( ResultItem $item ) :OriginalFileSource {
		return $this->source;
	}

	protected function isThemeOnWpOrg( OriginalFileSource $source ) :bool {
		$this->networkCalls[] = 'theme';
		return $this->themeOnWpOrg;
	}

	protected function fetchOriginalFile( OriginalFileSource $source, string $path ) :?string {
		$this->networkCalls[] = 'fetch';
		return $this->original;
	}

	protected function buildDiff( string $original, string $path ) :?array {
		$this->networkCalls[] = 'diff';
		return $this->diffResult;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScansResultsViewBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	public function test_build_prefers_summary_rows_and_hides_empty_asset_and_vulnerability_tabs() :void {
		$builder = new ScansResultsViewBuilderTestDouble(
			[
				[ 'key' => 'wp_files', 'label' => 'WP Files', 'count' => 2 ],
			],
			[
				[ 'key' => 'assessment', 'label' => 'Assessment' ],
			],
			$this->buildSectionPayload( 'rendered-wordpress', 2 ),
			$this->buildSectionPayload( 'rendered-plugins', 0 ),
			$this->buildSectionPayload( 'rendered-themes', 3 ),
			$this->buildSectionPayload( 'rendered-malware', 1 ),
			[
				'render_output' => 'rendered-file-locker',
				'render_data'   => [
					'vars' => [
						'file_locks' => [
							'count_items' => 0,
						],
					],
				],
			],
			[
				'count'    => 0,
				'status'   => 'good',
				'sections' => [],
			],
			true
		);

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'wordpress', 'themes', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertTrue( (bool)( $tabs[ 0 ][ 'is_active' ] ?? false ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'assessment_rows' ] ?? null );
		$this->assertSame( 'rendered-wordpress', $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? '' );
		$this->assertSame( 'rendered-themes', $renderData[ 'content' ][ 'section' ][ 'themes' ] ?? '' );
	}

	public function test_build_uses_assessment_rows_when_summary_is_empty_and_shows_vulnerabilities() :void {
		$builder = new ScansResultsViewBuilderTestDouble(
			[],
			[
				[ 'key' => 'wp_files', 'label' => 'WordPress Core Files' ],
			],
			$this->buildSectionPayload( 'rendered-wordpress', 9 ),
			$this->buildSectionPayload( 'rendered-plugins', 4 ),
			$this->buildSectionPayload( 'rendered-themes', 0 ),
			$this->buildSectionPayload( 'rendered-malware', 0 ),
			[
				'render_output' => 'rendered-file-locker',
				'render_data'   => [
					'vars' => [
						'file_locks' => [
							'count_items' => 2,
						],
					],
				],
			],
			[
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[ 'key' => 'vuln-plugin' ],
						],
					],
					'abandoned'  => [
						'label' => 'Abandoned Assets',
						'items' => [
							[ 'key' => 'abandoned-theme' ],
						],
					],
				],
			],
			false
		);

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'plugins', 'vulnerabilities', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'summary_rows' ] ?? null );
		$this->assertSame(
			[
				[ 'key' => 'wp_files', 'label' => 'WordPress Core Files' ],
			],
			$renderData[ 'vars' ][ 'assessment_rows' ] ?? null
		);
		$this->assertSame( 2, (int)( $tabs[ 2 ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'rendered-file-locker', $renderData[ 'content' ][ 'section' ][ 'filelocker' ] ?? '' );
	}

	private function buildSectionPayload( string $renderOutput, int $countItems ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'vars' => [
					'count_items' => $countItems,
				],
			],
		];
	}
}

class ScansResultsViewBuilderTestDouble extends ScansResultsViewBuilder {

	private array $summaryRows;
	private array $assessmentRows;
	private array $wordpressPayload;
	private array $pluginsPayload;
	private array $themesPayload;
	private array $malwarePayload;
	private array $fileLockerPayload;
	private array $vulnerabilities;
	private bool $wordpressEnabled;

	public function __construct(
		array $summaryRows,
		array $assessmentRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities,
		bool $wordpressEnabled
	) {
		$this->summaryRows = $summaryRows;
		$this->assessmentRows = $assessmentRows;
		$this->wordpressPayload = $wordpressPayload;
		$this->pluginsPayload = $pluginsPayload;
		$this->themesPayload = $themesPayload;
		$this->malwarePayload = $malwarePayload;
		$this->fileLockerPayload = $fileLockerPayload;
		$this->vulnerabilities = $vulnerabilities;
		$this->wordpressEnabled = $wordpressEnabled;
	}

	protected function cleanScanResultsState() :void {
	}

	protected function buildSummaryRows() :array {
		return $this->summaryRows;
	}

	protected function buildAssessmentRows() :array {
		return $this->assessmentRows;
	}

	protected function buildWordpressSectionPayload() :array {
		return $this->wordpressPayload;
	}

	protected function buildPluginsSectionPayload() :array {
		return $this->pluginsPayload;
	}

	protected function buildThemesSectionPayload() :array {
		return $this->themesPayload;
	}

	protected function buildMalwareSectionPayload() :array {
		return $this->malwarePayload;
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->fileLockerPayload;
	}

	protected function buildVulnerabilities() :array {
		return $this->vulnerabilities;
	}

	protected function isWordpressTabEnabled() :bool {
		return $this->wordpressEnabled;
	}
}

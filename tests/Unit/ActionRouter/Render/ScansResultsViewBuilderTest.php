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
			true,
			true,
			true,
			false,
			true
		);

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'wordpress', 'themes', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ 'summary', 'wordpress', 'plugins', 'themes', 'malware', 'file_locker' ], \array_column( $railTabs, 'key' ) );
		$this->assertTrue( (bool)( $tabs[ 0 ][ 'is_active' ] ?? false ) );
		$this->assertSame( 'critical', $railTabs[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'good', $railTabs[ 2 ][ 'status' ] ?? '' );
		$this->assertSame( [], $railTabs[ 2 ][ 'items' ] ?? null );
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
							[
								'key'         => 'vuln-plugin',
								'label'       => 'Vulnerable Plugin',
								'description' => '1 known vulnerability needs review.',
								'severity'    => 'critical',
								'count'       => 1,
							],
						],
					],
					'abandoned'  => [
						'label' => 'Abandoned Assets',
						'items' => [
							[
								'key'         => 'abandoned-theme',
								'label'       => 'Abandoned Theme',
								'description' => 'This asset appears to be abandoned and should be reviewed.',
								'severity'    => 'warning',
								'count'       => 1,
							],
						],
					],
				],
			],
			false,
			true,
			true,
			true,
			false
		);

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [ 'summary', 'plugins', 'vulnerabilities', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) );
		$this->assertSame( [ 'summary', 'plugins', 'themes', 'vulnerabilities', 'file_locker' ], \array_column( $railTabs, 'key' ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'summary_rows' ] ?? null );
		$this->assertSame(
			[
				[ 'key' => 'wp_files', 'label' => 'WordPress Core Files' ],
			],
			$renderData[ 'vars' ][ 'assessment_rows' ] ?? null
		);
		$this->assertSame( 2, (int)( $tabs[ 2 ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', $railTabs[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'good', $railTabs[ 2 ][ 'status' ] ?? '' );
		$this->assertSame( 'warning', $railTabs[ 4 ][ 'status' ] ?? '' );
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
	private bool $pluginsEnabled;
	private bool $themesEnabled;
	private bool $vulnerabilitiesEnabled;
	private bool $malwareEnabled;

	public function __construct(
		array $summaryRows,
		array $assessmentRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities,
		bool $wordpressEnabled,
		bool $pluginsEnabled = true,
		bool $themesEnabled = true,
		bool $vulnerabilitiesEnabled = true,
		bool $malwareEnabled = false
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
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
		$this->vulnerabilitiesEnabled = $vulnerabilitiesEnabled;
		$this->malwareEnabled = $malwareEnabled;
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

	protected function isPluginsRailTabEnabled() :bool {
		return $this->pluginsEnabled;
	}

	protected function isThemesRailTabEnabled() :bool {
		return $this->themesEnabled;
	}

	protected function isVulnerabilitiesRailTabEnabled() :bool {
		return $this->vulnerabilitiesEnabled;
	}

	protected function isMalwareRailTabEnabled() :bool {
		return $this->malwareEnabled;
	}

	protected function getAfsDisplayItems() :array {
		return [];
	}

	protected function getProblemFileLocks() :array {
		return [];
	}
}

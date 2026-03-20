<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestGeneral
};

abstract class ScansResultsViewBuilderTestCase extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
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
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		$GLOBALS[ 'wp_version' ] = '6.7';
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
		] );
	}

	protected function tearDown() :void {
		unset( $GLOBALS[ 'wp_version' ] );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	protected function findTabByKey( array $tabs, string $key ) :array {
		foreach ( $tabs as $tab ) {
			if ( ( $tab[ 'key' ] ?? '' ) === $key ) {
				return $tab;
			}
		}
		$this->fail( 'Tab "'.$key.'" not found in: '.\implode( ', ', \array_column( $tabs, 'key' ) ) );
		return [];
	}

	protected function makeDetailRow( string $title, string $status, ?int $countBadge = null ) :array {
		return [
			'title'         => $title,
			'description'   => '',
			'status'        => $status,
			'status_icon'   => null,
			'status_label'  => null,
			'count_badge'   => $countBadge,
			'badge_status'  => $countBadge !== null ? $status : null,
			'expandable'    => false,
			'expand_target' => '',
			'expansion'     => [],
			'explanations'  => [],
			'show_gear'     => false,
			'actions'       => [],
			'attributes'    => [],
			'section_label' => '',
		];
	}

	protected function makePluginThemeIssueRecord(
		string $assetType,
		string $key,
		string $title,
		string $subjectId,
		int $countBadge
	) :array {
		return [
			'key'               => $key,
			'panel_id'          => 'actions-queue-'.$assetType.'-card-'.$key,
			'panel_target'      => 'actions-queue-'.$assetType.'-'.$key,
			'expand_target'     => 'scan-files-'.$assetType.'-'.$key,
			'status'            => 'warning',
			'icon_class'        => $assetType === 'plugin' ? 'bi bi-plug-fill' : 'bi bi-palette-fill',
			'title'             => $title,
			'rail_title'        => '',
			'stat_text'         => $countBadge.' files need review',
			'meta_text'         => $subjectId,
			'show_meta_in_tile' => true,
			'count_badge'       => $countBadge,
			'actions'           => [
				[
					'type'       => 'deactivate',
					'label'      => 'Deactivate',
					'href'       => '/wp-admin/plugins.php',
					'icon'       => 'bi bi-power',
					'tooltip'    => 'Go to plugins',
					'attributes' => [],
				],
			],
			'table'             => [
				'subject_type' => $assetType,
				'subject_id'   => $subjectId,
			],
			'render_action'     => [],
		];
	}

	protected function makeAfsItem( string $flag, array $extra = [] ) :object {
		return (object)\array_merge( [
			'path_fragment'   => 'test/'.\uniqid( '', true ).'.php',
			'is_in_core'      => 0,
			'is_mal'          => 0,
			'is_missing'      => 0,
			'is_checksumfail' => 0,
			'is_unrecognised' => 0,
			'is_unidentified' => 0,
		], $extra, [ $flag => 1 ] );
	}

	protected function buildSectionPayload( string $renderOutput, int $countItems ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'vars' => [
					'count_items' => $countItems,
				],
			],
		];
	}

	protected function buildFileLockerPayload( string $renderOutput, bool $isEnabled, int $countItems = 0 ) :array {
		return [
			'render_output' => $renderOutput,
			'render_data'   => [
				'flags' => [
					'is_enabled'    => $isEnabled,
					'is_restricted' => false,
				],
				'vars' => [
					'file_locks' => [
						'count_items' => $countItems,
					],
				],
			],
		];
	}

	protected function buildEmptyVulnerabilities() :array {
		return [
			'count'    => 0,
			'status'   => 'good',
			'sections' => [],
		];
	}

	protected function createBuilder( array $overrides = [] ) :ScansResultsViewBuilderTestDouble {
		return new ScansResultsViewBuilderTestDouble(
			$overrides[ 'summaryRows' ] ?? [],
			$overrides[ 'assessmentRows' ] ?? [],
			$overrides[ 'wordpressPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'pluginsPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'themesPayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'malwarePayload' ] ?? $this->buildSectionPayload( '', 0 ),
			$overrides[ 'fileLockerPayload' ] ?? $this->buildFileLockerPayload( '', false ),
			$overrides[ 'vulnerabilities' ] ?? $this->buildEmptyVulnerabilities(),
			$overrides[ 'wordpressEnabled' ] ?? false,
			$overrides[ 'pluginsEnabled' ] ?? false,
			$overrides[ 'themesEnabled' ] ?? false,
			$overrides[ 'vulnerabilitiesEnabled' ] ?? false,
			$overrides[ 'malwareEnabled' ] ?? false,
			$overrides[ 'afsDisplayItems' ] ?? [],
			$overrides[ 'problemFileLocks' ] ?? [],
			$overrides[ 'goodFileLocks' ] ?? [],
			$overrides[ 'pluginRailItems' ] ?? [],
			$overrides[ 'themeRailItems' ] ?? [],
			$overrides[ 'pluginIssueRecords' ] ?? [],
			$overrides[ 'themeIssueRecords' ] ?? [],
			$overrides[ 'tabAvailability' ] ?? []
		);
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
	private array $afsDisplayItems;
	private array $problemLocks;
	private array $goodLocks;
	private array $pluginRailItems;
	private array $themeRailItems;
	private array $pluginIssueRecords;
	private array $themeIssueRecords;
	private array $tabAvailability;

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
		bool $pluginsEnabled = false,
		bool $themesEnabled = false,
		bool $vulnerabilitiesEnabled = false,
		bool $malwareEnabled = false,
		array $afsDisplayItems = [],
		array $problemLocks = [],
		array $goodLocks = [],
		array $pluginRailItems = [],
		array $themeRailItems = [],
		array $pluginIssueRecords = [],
		array $themeIssueRecords = [],
		array $tabAvailability = []
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
		$this->afsDisplayItems = $afsDisplayItems;
		$this->problemLocks = $problemLocks;
		$this->goodLocks = $goodLocks;
		$this->pluginRailItems = $pluginRailItems;
		$this->themeRailItems = $themeRailItems;
		$this->pluginIssueRecords = $pluginIssueRecords;
		$this->themeIssueRecords = $themeIssueRecords;
		$this->tabAvailability = $tabAvailability;
	}

	protected function buildSummaryRows() :array {
		return \array_values( \array_map( static fn( array $row ) :array => \array_merge( [
			'key'      => '',
			'label'    => '',
			'text'     => '',
			'severity' => 'warning',
			'count'    => 0,
			'action'   => '',
			'href'     => '',
		], $row ), $this->summaryRows ) );
	}

	protected function buildAssessmentRows() :array {
		return \array_values( \array_map( static fn( array $row ) :array => \array_merge( [
			'key'               => '',
			'label'             => '',
			'status'            => 'good',
			'description'       => '',
			'status_icon_class' => '',
			'status_label'      => '',
		], $row ), $this->assessmentRows ) );
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
		return $this->afsDisplayItems;
	}

	protected function getProblemFileLocks() :array {
		return $this->problemLocks;
	}

	protected function getGoodFileLocks() :array {
		return $this->goodLocks;
	}

	protected function isFileLockerEnabled() :bool {
		return true;
	}

	protected function isPremiumActive() :bool {
		return true;
	}

	protected function buildPluginThemeRailItemsDirect( string $assetType ) :array {
		$items = $assetType === 'plugin' ? $this->pluginRailItems : $this->themeRailItems;
		return !empty( $items ) ? $items : parent::buildPluginThemeRailItemsDirect( $assetType );
	}

	protected function buildPluginThemeIssueRecords( string $assetType, ?array $resultsDisplayOptions = null ) :array {
		return $assetType === 'plugin' ? $this->pluginIssueRecords : $this->themeIssueRecords;
	}

	protected function getRailTabAvailability( string $tabKey ) :array {
		if ( isset( $this->tabAvailability[ $tabKey ] ) ) {
			return $this->tabAvailability[ $tabKey ];
		}

		switch ( $tabKey ) {
			case 'wordpress':
				$isAvailable = $this->wordpressEnabled;
				break;
			case 'plugins':
				$isAvailable = $this->pluginsEnabled;
				break;
			case 'themes':
				$isAvailable = $this->themesEnabled;
				break;
			case 'vulnerabilities':
				$isAvailable = $this->vulnerabilitiesEnabled;
				break;
			case 'malware':
				$isAvailable = $this->malwareEnabled;
				break;
			case 'file_locker':
				$isAvailable = true;
				break;
			default:
				$isAvailable = false;
				break;
		}

		return [
			'is_available'          => $isAvailable,
			'show_in_actions_queue' => \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker' ], true )
				|| ( $tabKey === 'wordpress' && $this->wordpressEnabled ),
			'disabled_message'      => '',
			'disabled_status'       => 'neutral',
		];
	}

	protected function buildAjaxRenderActionData( string $actionClass, array $aux = [] ) :array {
		$slug = $actionClass === \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff::class
			? 'filelocker_showdiff'
			: 'render_action';

		return \array_merge( [
			'render_slug' => $slug,
		], $aux );
	}
}

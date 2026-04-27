<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ScanResultsTableContractBuilder,
	ScansResultsViewBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestGeneral
};
use FernleafSystems\Wordpress\Services\Core\{
	Request,
	Users
};

abstract class ScansResultsViewBuilderTestCase extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural );
		Functions\when( 'sanitize_key' )->alias( static fn( string $value ) :string => \strtolower( \preg_replace( '/[^a-z0-9_]/', '', $value ) ?? '' ) );
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ) :string => '/wp-admin/'.\ltrim( $path, '/' ) );
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
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
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		$GLOBALS[ 'wp_version' ] = '6.7';
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers'   => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		unset( $GLOBALS[ 'wp_version' ] );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
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
			'body_notice'         => '',
			'body_notice_variant' => '',
			'panel_data'          => [
				'actions-queue-asset-panel-loaded' => '1',
				'actions-queue-asset-panel-lazy'   => '0',
			],
			'actions'           => [
				[
					'type'         => 'deactivate',
					'label'        => 'Deactivate',
					'href'         => '/wp-admin/plugins.php',
					'icon_class'   => 'bi bi-power',
					'tooltip_attr' => 'Go to plugins',
					'attributes'   => [],
				],
			],
			'table'             => ( new ScanResultsTableContractBuilder() )->buildFileStatus(
				$assetType,
				$subjectId,
				'/queue/scans',
				[
					'display_context' => 'actions_queue',
				]
			),
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
			$overrides[ 'vulnerabilities' ] ?? $this->buildEmptyVulnerabilities(),
			$overrides[ 'pluginsEnabled' ] ?? false,
			$overrides[ 'themesEnabled' ] ?? false,
			$overrides[ 'vulnerabilitiesEnabled' ] ?? false,
			$overrides[ 'problemFileLocks' ] ?? [],
			$overrides[ 'pendingFileLockDisplays' ] ?? [],
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

	private array $vulnerabilities;
	private bool $pluginsEnabled;
	private bool $themesEnabled;
	private bool $vulnerabilitiesEnabled;
	private array $problemLocks;
	private array $pendingFileLockDisplays;
	private array $goodLocks;
	private array $pluginRailItems;
	private array $themeRailItems;
	private array $pluginIssueRecords;
	private array $themeIssueRecords;
	private array $tabAvailability;

	public function __construct(
		array $vulnerabilities,
		bool $pluginsEnabled = false,
		bool $themesEnabled = false,
		bool $vulnerabilitiesEnabled = false,
		array $problemLocks = [],
		array $pendingFileLockDisplays = [],
		array $goodLocks = [],
		array $pluginRailItems = [],
		array $themeRailItems = [],
		array $pluginIssueRecords = [],
		array $themeIssueRecords = [],
		array $tabAvailability = []
	) {
		$this->vulnerabilities = $vulnerabilities;
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
		$this->vulnerabilitiesEnabled = $vulnerabilitiesEnabled;
		$this->problemLocks = $problemLocks;
		$this->pendingFileLockDisplays = $pendingFileLockDisplays;
		$this->goodLocks = $goodLocks;
		$this->pluginRailItems = $pluginRailItems;
		$this->themeRailItems = $themeRailItems;
		$this->pluginIssueRecords = $pluginIssueRecords;
		$this->themeIssueRecords = $themeIssueRecords;
		$this->tabAvailability = $tabAvailability;
	}

	protected function buildVulnerabilities() :array {
		return $this->vulnerabilities;
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

	protected function getProblemFileLocks() :array {
		return $this->problemLocks;
	}

	protected function getGoodFileLocks() :array {
		return $this->goodLocks;
	}

	protected function getPendingFileLockDisplays() :array {
		return $this->pendingFileLockDisplays;
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
			case 'plugins':
				$isAvailable = $this->pluginsEnabled;
				break;
			case 'themes':
				$isAvailable = $this->themesEnabled;
				break;
			case 'vulnerabilities':
				$isAvailable = $this->vulnerabilitiesEnabled;
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
			'show_in_actions_queue' => \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'file_locker' ], true ),
			'show_in_fix_now'       => \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'abandoned', 'file_locker' ], true ),
			'disabled_reason'       => '',
			'disabled_message'      => '',
			'disabled_status'       => 'neutral',
			'disabled_actions'      => [],
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

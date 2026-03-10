<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	FileLocker,
	Malware,
	Plugins,
	Themes,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\CleanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class ScansResultsViewBuilder {

	use PluginControllerConsumer;

	public function build() :array {
		$this->cleanScanResultsState();

		$summaryRows = $this->buildSummaryRows();
		$assessmentRows = empty( $summaryRows ) ? $this->buildAssessmentRows() : [];
		$wordpressPayload = $this->buildWordpressSectionPayload();
		$pluginsPayload = $this->buildPluginsSectionPayload();
		$themesPayload = $this->buildThemesSectionPayload();
		$malwarePayload = $this->buildMalwareSectionPayload();
		$fileLockerPayload = $this->buildFileLockerSectionPayload();
		$vulnerabilities = $this->buildVulnerabilities();
		$legacyTabs = $this->buildLegacyTabs(
			$summaryRows,
			$wordpressPayload,
			$pluginsPayload,
			$themesPayload,
			$malwarePayload,
			$fileLockerPayload,
			$vulnerabilities
		);
		$railTabs = $this->buildRailTabs(
			$summaryRows,
			$assessmentRows,
			$wordpressPayload,
			$pluginsPayload,
			$themesPayload,
			$malwarePayload,
			$fileLockerPayload,
			$vulnerabilities
		);

		return [
			'vars'    => [
				'tabs'            => $legacyTabs,
				'rail_tabs'       => $railTabs,
				'summary_rows'    => $summaryRows,
				'assessment_rows' => $assessmentRows,
				'vulnerabilities' => $vulnerabilities,
			],
			'content' => [
				'section' => [
					'wordpress'  => (string)( $wordpressPayload[ 'render_output' ] ?? '' ),
					'plugins'    => (string)( $pluginsPayload[ 'render_output' ] ?? '' ),
					'themes'     => (string)( $themesPayload[ 'render_output' ] ?? '' ),
					'malware'    => (string)( $malwarePayload[ 'render_output' ] ?? '' ),
					'filelocker' => (string)( $fileLockerPayload[ 'render_output' ] ?? '' ),
				],
			],
		];
	}

	protected function cleanScanResultsState() :void {
		( new CleanQueue() )->execute();
		foreach ( self::con()->comps->scans->getAllScanCons() as $scanCon ) {
			$scanCon->cleanStalesResults();
		}
	}

	protected function buildSummaryRows() :array {
		return ( new AttentionItemsProvider() )->buildScanItems();
	}

	protected function buildAssessmentRows() :array {
		return ( new ActionsQueueLandingAssessmentBuilder() )->build()[ 'scans' ] ?? [];
	}

	protected function buildWordpressSectionPayload() :array {
		return $this->actionPayload( Wordpress::class );
	}

	protected function buildPluginsSectionPayload() :array {
		return $this->actionPayload( Plugins::class );
	}

	protected function buildThemesSectionPayload() :array {
		return $this->actionPayload( Themes::class );
	}

	protected function buildMalwareSectionPayload() :array {
		return $this->actionPayload( Malware::class );
	}

	protected function buildFileLockerSectionPayload() :array {
		return $this->actionPayload( FileLocker::class );
	}

	protected function buildVulnerabilities() :array {
		return ( new ScansVulnerabilitiesBuilder() )->build();
	}

	protected function isWordpressTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledWpCore();
	}

	protected function isPluginsRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledPlugins();
	}

	protected function isThemesRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isScanEnabledThemes();
	}

	protected function isVulnerabilitiesRailTabEnabled() :bool {
		$scansCon = self::con()->comps->scans;
		return $scansCon->WPV()->isEnabled() || $scansCon->APC()->isEnabled();
	}

	protected function isMalwareRailTabEnabled() :bool {
		return self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP();
	}

	/**
	 * @param array<int,array{key:string,label:string,count:int,is_shown?:bool}> $definitions
	 * @return list<array<string,mixed>>
	 */
	protected function buildTabs( array $definitions ) :array {
		$tabs = [];
		foreach ( $definitions as $definition ) {
			if ( !( $definition[ 'is_shown' ] ?? true ) ) {
				continue;
			}

			$paneId = 'h-tabs-'.$definition[ 'key' ];
			$baseTab = [
				'key'       => $definition[ 'key' ],
				'pane_id'   => $paneId,
				'nav_id'    => $paneId.'-tab',
				'label'     => $definition[ 'label' ],
				'count'     => (int)$definition[ 'count' ],
				'is_active' => empty( $tabs ),
				'target'    => '#'.$paneId,
				'controls'  => $paneId,
			];
			unset( $definition[ 'key' ], $definition[ 'label' ], $definition[ 'count' ], $definition[ 'is_shown' ] );
			$tabs[] = \array_merge( $baseTab, $definition );
		}
		return $tabs;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildLegacyTabs(
		array $summaryRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities
	) :array {
		return $this->buildTabs( [
			[
				'key'   => 'summary',
				'label' => __( 'Summary', 'wp-simple-firewall' ),
				'count' => \count( $summaryRows ),
			],
			[
				'key'      => 'wordpress',
				'label'    => __( 'WordPress', 'wp-simple-firewall' ),
				'count'    => $this->extractSectionCount( $wordpressPayload ),
				'is_shown' => $this->isWordpressTabEnabled(),
			],
			[
				'key'      => 'plugins',
				'label'    => __( 'Plugins', 'wp-simple-firewall' ),
				'count'    => $this->extractSectionCount( $pluginsPayload ),
				'is_shown' => $this->extractSectionCount( $pluginsPayload ) > 0,
			],
			[
				'key'      => 'themes',
				'label'    => __( 'Themes', 'wp-simple-firewall' ),
				'count'    => $this->extractSectionCount( $themesPayload ),
				'is_shown' => $this->extractSectionCount( $themesPayload ) > 0,
			],
			[
				'key'      => 'vulnerabilities',
				'label'    => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'count'    => (int)( $vulnerabilities[ 'count' ] ?? 0 ),
				'is_shown' => (int)( $vulnerabilities[ 'count' ] ?? 0 ) > 0,
			],
			[
				'key'   => 'malware',
				'label' => __( 'Malware', 'wp-simple-firewall' ),
				'count' => $this->extractSectionCount( $malwarePayload ),
			],
			[
				'key'   => 'file_locker',
				'label' => __( 'File Locker', 'wp-simple-firewall' ),
				'count' => $this->extractSectionCount( $fileLockerPayload ),
			],
		] );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildRailTabs(
		array $summaryRows,
		array $assessmentRows,
		array $wordpressPayload,
		array $pluginsPayload,
		array $themesPayload,
		array $malwarePayload,
		array $fileLockerPayload,
		array $vulnerabilities
	) :array {
		$definitions = [
			[
				'key'    => 'summary',
				'label'  => __( 'Summary', 'wp-simple-firewall' ),
				'count'  => \count( $summaryRows ),
				'status' => 'good',
				'items'  => $this->buildSummaryRailItems( $summaryRows, $assessmentRows ),
			],
		];

		if ( $this->isWordpressTabEnabled() ) {
			$definitions[] = [
				'key'    => 'wordpress',
				'label'  => __( 'WordPress', 'wp-simple-firewall' ),
				'count'  => $this->extractSectionCount( $wordpressPayload ),
				'status' => $this->extractSectionCount( $wordpressPayload ) > 0 ? 'critical' : 'good',
				'items'  => $this->buildWordpressRailItems(),
			];
		}

		if ( $this->isPluginsRailTabEnabled() ) {
			$pluginsCount = $this->extractSectionCount( $pluginsPayload );
			$definitions[] = [
				'key'    => 'plugins',
				'label'  => __( 'Plugins', 'wp-simple-firewall' ),
				'count'  => $pluginsCount,
				'status' => $pluginsCount > 0 ? 'warning' : 'good',
				'items'  => $this->buildPluginThemeRailItems( $pluginsPayload, 'plugins' ),
			];
		}

		if ( $this->isThemesRailTabEnabled() ) {
			$themesCount = $this->extractSectionCount( $themesPayload );
			$definitions[] = [
				'key'    => 'themes',
				'label'  => __( 'Themes', 'wp-simple-firewall' ),
				'count'  => $themesCount,
				'status' => $themesCount > 0 ? 'warning' : 'good',
				'items'  => $this->buildPluginThemeRailItems( $themesPayload, 'themes' ),
			];
		}

		if ( $this->isVulnerabilitiesRailTabEnabled() ) {
			$definitions[] = [
				'key'    => 'vulnerabilities',
				'label'  => __( 'Vulnerabilities', 'wp-simple-firewall' ),
				'count'  => (int)( $vulnerabilities[ 'count' ] ?? 0 ),
				'status' => $this->buildVulnerabilitiesRailStatus( $vulnerabilities ),
				'items'  => $this->buildVulnerabilitiesRailItems( $vulnerabilities ),
			];
		}

		if ( $this->isMalwareRailTabEnabled() ) {
			$malwareCount = $this->extractSectionCount( $malwarePayload );
			$definitions[] = [
				'key'    => 'malware',
				'label'  => __( 'Malware', 'wp-simple-firewall' ),
				'count'  => $malwareCount,
				'status' => $malwareCount > 0 ? 'critical' : 'good',
				'items'  => $this->buildMalwareRailItems(),
			];
		}

		$fileLockerCount = $this->extractSectionCount( $fileLockerPayload );
		$definitions[] = [
			'key'    => 'file_locker',
			'label'  => __( 'File Locker', 'wp-simple-firewall' ),
			'count'  => $fileLockerCount,
			'status' => $fileLockerCount > 0 ? 'warning' : 'good',
			'items'  => $this->buildFileLockerRailItems( $fileLockerPayload ),
		];

		$nonSummaryStatuses = \array_column(
			\array_filter(
				$definitions,
				static fn( array $definition ) :bool => ( $definition[ 'key' ] ?? '' ) !== 'summary'
			),
			'status'
		);
		$definitions[ 0 ][ 'status' ] = StatusPriority::highest( $nonSummaryStatuses, 'good' );

		return $this->buildTabs( $definitions );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildSummaryRailItems( array $summaryRows, array $assessmentRows ) :array {
		if ( !empty( $summaryRows ) ) {
			return \array_values( \array_map( function ( array $item ) :array {
				$severity = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
				return $this->buildDetailRow(
					(string)( $item[ 'label' ] ?? '' ),
					(string)( $item[ 'text' ] ?? '' ),
					$severity,
					(int)( $item[ 'count' ] ?? 0 ),
					$severity,
					$this->buildActionsForHref(
						(string)( $item[ 'action' ] ?? '' ),
						(string)( $item[ 'href' ] ?? '' )
					)
				);
			}, $summaryRows ) );
		}

		return \array_values( \array_map( fn( array $item ) :array => $this->buildDetailRow(
			(string)( $item[ 'label' ] ?? '' ),
			(string)( $item[ 'description' ] ?? '' ),
			StatusPriority::normalize( (string)( $item[ 'status' ] ?? 'good' ), 'good' ),
			null,
			null,
			[],
			(string)( $item[ 'status_icon_class' ] ?? '' ),
			(string)( $item[ 'status_label' ] ?? '' )
		), $assessmentRows ) );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildPluginThemeRailItems( array $payload, string $key ) :array {
		return \array_values( \array_map( function ( array $item ) :array {
			$severity = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
			$cta = \is_array( $item[ 'cta' ] ?? null ) ? $item[ 'cta' ] : [];
			return $this->buildDetailRow(
				(string)( $item[ 'label' ] ?? '' ),
				(string)( $item[ 'description' ] ?? '' ),
				$severity,
				(int)( $item[ 'count' ] ?? 0 ),
				$severity,
				$this->buildActionsForHref(
					(string)( $cta[ 'label' ] ?? '' ),
					(string)( $cta[ 'href' ] ?? '' )
				)
			);
		}, $this->extractPayloadRows( $payload, $key ) ) );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildVulnerabilitiesRailItems( array $vulnerabilities ) :array {
		$items = [];
		foreach ( \is_array( $vulnerabilities[ 'sections' ] ?? null ) ? $vulnerabilities[ 'sections' ] : [] as $section ) {
			$sectionLabel = (string)( $section[ 'label' ] ?? '' );
			foreach ( \is_array( $section[ 'items' ] ?? null ) ? $section[ 'items' ] : [] as $item ) {
				$severity = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
				$cta = \is_array( $item[ 'cta' ] ?? null ) ? $item[ 'cta' ] : [];
				$items[] = $this->buildDetailRow(
					(string)( $item[ 'label' ] ?? '' ),
					(string)( $item[ 'description' ] ?? '' ),
					$severity,
					isset( $item[ 'count' ] ) ? (int)$item[ 'count' ] : null,
					$severity,
					$this->buildActionsForHref(
						(string)( $cta[ 'label' ] ?? '' ),
						(string)( $cta[ 'href' ] ?? '' )
					),
					null,
					null,
					$sectionLabel
				);
			}
		}
		return $items;
	}

	protected function buildVulnerabilitiesRailStatus( array $vulnerabilities ) :string {
		$statuses = [];
		foreach ( \is_array( $vulnerabilities[ 'sections' ] ?? null ) ? $vulnerabilities[ 'sections' ] : [] as $section ) {
			foreach ( \is_array( $section[ 'items' ] ?? null ) ? $section[ 'items' ] : [] as $item ) {
				$statuses[] = StatusPriority::normalize( (string)( $item[ 'severity' ] ?? 'warning' ), 'warning' );
			}
		}
		return StatusPriority::highest( $statuses, 'good' );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildWordpressRailItems() :array {
		$items = [];
		foreach ( $this->getAfsDisplayItems() as $item ) {
			if ( empty( $item->is_in_core ) ) {
				continue;
			}
			$items[] = $this->buildDetailRow(
				(string)$item->path_fragment,
				$this->describeWordpressResultItem( $item ),
				'critical'
			);
		}
		return $items;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildMalwareRailItems() :array {
		$items = [];
		foreach ( $this->getAfsDisplayItems() as $item ) {
			if ( empty( $item->is_mal ) ) {
				continue;
			}
			$items[] = $this->buildDetailRow(
				(string)$item->path_fragment,
				$this->describeMalwareResultItem( $item ),
				'critical'
			);
		}
		return $items;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function buildFileLockerRailItems( array $payload ) :array {
		$flags = \is_array( $payload[ 'render_data' ][ 'flags' ] ?? null ) ? $payload[ 'render_data' ][ 'flags' ] : [];
		if ( empty( $flags[ 'is_enabled' ] ) || !empty( $flags[ 'is_restricted' ] ) ) {
			return [];
		}

		return \array_values( \array_map( fn( $lock ) :array => $this->buildDetailRow(
			(string)( $lock->path ?? '' ),
			$this->describeFileLockerRecord( $lock ),
			'warning'
		), $this->getProblemFileLocks() ) );
	}

	/**
	 * @return list<object>
	 */
	protected function getAfsDisplayItems() :array {
		try {
			return self::con()->comps->scans->AFS()->getResultsForDisplay()->getAllItems();
		}
		catch ( \Throwable $e ) {
			return [];
		}
	}

	protected function getProblemFileLocks() :array {
		return ( new LoadFileLocks() )->withProblems();
	}

	private function actionPayload( string $actionClass ) :array {
		return self::con()->action_router->action( $actionClass )->payload();
	}

	private function extractSectionCount( array $payload ) :int {
		$renderData = \is_array( $payload[ 'render_data' ] ?? null ) ? $payload[ 'render_data' ] : [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];

		if ( isset( $vars[ 'count_items' ] ) ) {
			return (int)$vars[ 'count_items' ];
		}
		if ( isset( $renderData[ 'count' ] ) ) {
			return (int)$renderData[ 'count' ];
		}

		return (int)( $vars[ 'file_locks' ][ 'count_items' ] ?? 0 );
	}

	private function extractPayloadRows( array $payload, string $key ) :array {
		$vars = \is_array( $payload[ 'render_data' ][ 'vars' ] ?? null ) ? $payload[ 'render_data' ][ 'vars' ] : [];
		return \is_array( $vars[ $key ] ?? null ) ? \array_values( $vars[ $key ] ) : [];
	}

	private function buildDetailRow(
		string $title,
		string $description,
		string $status,
		?int $countBadge = null,
		?string $badgeStatus = null,
		array $actions = [],
		?string $statusIcon = null,
		?string $statusLabel = null,
		?string $sectionLabel = null
	) :array {
		$row = [
			'title'        => $title,
			'description'  => $description,
			'status'       => $status,
			'status_icon'  => $statusIcon,
			'status_label' => $statusLabel,
			'count_badge'  => $countBadge,
			'badge_status' => $badgeStatus,
			'expandable'   => false,
			'explanations' => [],
			'show_gear'    => false,
			'actions'      => $actions,
		];
		if ( $sectionLabel !== null ) {
			$row[ 'section_label' ] = $sectionLabel;
		}
		return $row;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildActionsForHref( string $label, string $href ) :array {
		if ( $label === '' || $href === '' ) {
			return [];
		}

		return [
			[
				'type'    => 'navigate',
				'label'   => $label,
				'href'    => $href,
				'icon'    => 'bi bi-arrow-right-circle-fill',
				'tooltip' => null,
			],
		];
	}

	private function describeWordpressResultItem( object $item ) :string {
		if ( !empty( $item->is_missing ) ) {
			return __( 'File is missing.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_checksumfail ) ) {
			return __( 'File has been modified.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_unrecognised ) ) {
			return __( 'File is unrecognised.', 'wp-simple-firewall' );
		}
		if ( !empty( $item->is_unidentified ) ) {
			return __( 'File is unidentified.', 'wp-simple-firewall' );
		}

		$statuses = \method_exists( $item, 'getStatusForHuman' ) ? \array_values( $item->getStatusForHuman() ) : [];
		return empty( $statuses )
			? __( 'WordPress core file needs review.', 'wp-simple-firewall' )
			: \implode( ', ', $statuses );
	}

	private function describeMalwareResultItem( object $item ) :string {
		$statuses = \method_exists( $item, 'getStatusForHuman' ) ? \array_values( $item->getStatusForHuman() ) : [];
		if ( !empty( $statuses ) ) {
			return \implode( ', ', $statuses );
		}
		return __( 'Potential malware detected.', 'wp-simple-firewall' );
	}

	private function describeFileLockerRecord( object $lock ) :string {
		return empty( $lock->hash_current )
			? __( 'Locked file is missing.', 'wp-simple-firewall' )
			: __( 'File has been modified since the lock was created.', 'wp-simple-firewall' );
	}
}

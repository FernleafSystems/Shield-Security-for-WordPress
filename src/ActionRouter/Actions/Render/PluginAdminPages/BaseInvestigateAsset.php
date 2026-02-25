<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract,
	Actions\Investigation\InvestigationSubjectResolver,
	Actions\InvestigationTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\PluginThemesBase;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\{
	ForActivityLog as InvestigationActivityTableBuilder,
	ForFileScanResults as InvestigationFileScanResultsTableBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationSubjectWheres;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

abstract class BaseInvestigateAsset extends BasePluginAdminPage {

	use InvestigateAssetOptionsBuilder;

	private $assetDataBuilder;

	protected function getLookupValue( string $queryKey ) :string {
		return \trim( sanitize_text_field( (string)Services::Request()->query( $queryKey, '' ) ) );
	}

	protected function buildLookupRouteContract( string $subNav ) :array {
		return [
			'page'    => self::con()->plugin_urls->rootAdminPageSlug(),
			'nav'     => PluginNavs::NAV_ACTIVITY,
			'nav_sub' => $subNav,
		];
	}

	protected function resolvePluginByLookup( string $lookup ) {
		$lookup = \trim( $lookup );
		if ( empty( $lookup ) ) {
			return null;
		}

		$matched = $this->normalizeAssetLookup( InvestigationTableContract::SUBJECT_TYPE_PLUGIN, $lookup );
		return empty( $matched ) ? null : Services::WpPlugins()->getPluginAsVo( $matched, true );
	}

	protected function resolveThemeByLookup( string $lookup ) {
		$lookup = \trim( $lookup );
		if ( empty( $lookup ) ) {
			return null;
		}

		$matched = $this->normalizeAssetLookup( InvestigationTableContract::SUBJECT_TYPE_THEME, $lookup );
		return empty( $matched ) ? null : Services::WpThemes()->getThemeAsVo( $matched, true );
	}

	protected function buildPluginLookupOptions() :array {
		return $this->buildAssetOptions(
			Services::WpPlugins()->getPluginsAsVo(),
			'file'
		);
	}

	protected function buildThemeLookupOptions() :array {
		return $this->buildAssetOptions(
			Services::WpThemes()->getThemesAsVo(),
			'stylesheet'
		);
	}

	protected function buildAssetTabsPayload( string $subjectKey, array $counts, bool $includeVulnerabilities ) :array {
		$subjectKey = \strtolower( \trim( $subjectKey ) );
		$idPrefix = 'tabInvestigate'.\ucfirst( $subjectKey );

		$tabs = [
			'overview'    => [
				'pane_id'   => $idPrefix.'Overview',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-overview',
				'label'     => __( 'Overview', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => true,
			],
			'file_status' => [
				'pane_id'   => $idPrefix.'FileStatus',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-file-status',
				'label'     => __( 'File Status', 'wp-simple-firewall' ),
				'count'     => (int)( $counts[ 'file_status' ] ?? 0 ),
				'is_active' => false,
			],
			'activity'    => [
				'pane_id'   => $idPrefix.'Activity',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-activity',
				'label'     => __( 'Activity', 'wp-simple-firewall' ),
				'count'     => (int)( $counts[ 'activity' ] ?? 0 ),
				'is_active' => false,
			],
		];

		if ( $includeVulnerabilities ) {
			$tabs = [
				'overview'        => $tabs[ 'overview' ],
				'file_status'     => $tabs[ 'file_status' ],
				'vulnerabilities' => [
					'pane_id'   => $idPrefix.'Vulnerabilities',
					'nav_id'    => 'tab-navlink-'.$subjectKey.'-vulnerabilities',
					'label'     => __( 'Vulnerabilities', 'wp-simple-firewall' ),
					'count'     => (int)( $counts[ 'vulnerabilities' ] ?? 0 ),
					'is_active' => false,
				],
				'activity'        => $tabs[ 'activity' ],
			];
		}

		foreach ( $tabs as $key => $tab ) {
			$tabs[ $key ][ 'target' ] = '#'.$tab[ 'pane_id' ];
			$tabs[ $key ][ 'controls' ] = $tab[ 'pane_id' ];
		}

		return $tabs;
	}

	protected function buildRailNavItemsFromTabs( array $tabs ) :array {
		$items = [];
		foreach ( $tabs as $tab ) {
			$count = $tab[ 'count' ];
			$items[] = [
				'target'   => $tab[ 'target' ],
				'id'       => $tab[ 'nav_id' ],
				'controls' => $tab[ 'controls' ],
				'label'    => $count === null
					? (string)$tab[ 'label' ]
					: \sprintf( '%s (%d)', (string)$tab[ 'label' ], (int)$count ),
				'is_focus' => (bool)$tab[ 'is_active' ],
			];
		}
		return $items;
	}

	protected function buildAssetTables( string $subjectType, string $subjectId, string $activitySearchToken ) :array {
		$tableAction = ActionData::Build( InvestigationTableAction::class );

		return [
			'file_status' => $this->buildTableContainerContract(
				__( 'File Status', 'wp-simple-firewall' ),
				'warning',
				InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
				$subjectType,
				$subjectId,
				( new InvestigationFileScanResultsTableBuilder() )->setSubject( $subjectType, $subjectId )->buildRaw(),
				$tableAction,
				self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS )
			),
			'activity'    => $this->buildTableContainerContract(
				__( 'Activity', 'wp-simple-firewall' ),
				'warning',
				InvestigationTableContract::TABLE_TYPE_ACTIVITY,
				$subjectType,
				$subjectId,
				( new InvestigationActivityTableBuilder() )->setSubject( $subjectType, $subjectId )->buildRaw(),
				$tableAction,
				$this->buildFullLogHrefWithSearch( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS, $activitySearchToken )
			),
		];
	}

	protected function buildFullLogHrefWithSearch( string $nav, string $subNav, string $search ) :string {
		return URL::Build(
			self::con()->plugin_urls->adminTopNav( $nav, $subNav ),
			[
				'search' => $search,
			]
		);
	}

	protected function buildTableContainerContract(
		string $title,
		string $status,
		string $tableType,
		string $subjectType,
		string $subjectId,
		array $datatablesInit,
		array $tableAction,
		string $fullLogHref
	) :array {
		return [
			'title'           => $title,
			'status'          => $status,
			'table_type'      => $tableType,
			'subject_type'    => $subjectType,
			'subject_id'      => $subjectId,
			'datatables_init' => $datatablesInit,
			'table_action'    => $tableAction,
			'full_log_href'   => $fullLogHref,
			'full_log_text'   => __( 'Full Log', 'wp-simple-firewall' ),
		];
	}

	protected function buildPluginScanData( $plugin ) :array {
		return $this->getAssetDataBuilder()->exposeBuildPluginData( $plugin );
	}

	protected function buildThemeScanData( $theme ) :array {
		return $this->getAssetDataBuilder()->exposeBuildThemeData( $theme );
	}

	protected function buildVulnerabilityData( string $subjectId, string $lookupHref ) :array {
		try {
			$items = self::con()->comps->scans->WPV()->getResultsForDisplay()->getItemsForSlug( $subjectId );
		}
		catch ( \Throwable $e ) {
			$items = [];
		}
		$count = \count( $items );

		return [
			'count'       => $count,
			'status'      => $count > 0 ? 'critical' : 'good',
			'title'       => __( 'Known Vulnerabilities', 'wp-simple-firewall' ),
			'summary'     => $count > 0
				? \sprintf( _n( '%d vulnerability detected for this asset.', '%d vulnerabilities detected for this asset.', $count, 'wp-simple-firewall' ), $count )
				: __( 'No known vulnerabilities were detected for this asset in the current scan results.', 'wp-simple-firewall' ),
			'lookup_href' => $lookupHref,
			'lookup_text' => __( 'Vulnerability Lookup', 'wp-simple-firewall' ),
		];
	}

	protected function countFileScanResultsForSubject( string $subjectType, string $subjectId ) :int {
		$subjectType = \strtolower( \trim( $subjectType ) );
		switch ( $subjectType ) {
			case InvestigationTableContract::SUBJECT_TYPE_PLUGIN:
			case InvestigationTableContract::SUBJECT_TYPE_THEME:
				$wheres = InvestigationSubjectWheres::forAssetSlug( $subjectId );
				break;
			case InvestigationTableContract::SUBJECT_TYPE_CORE:
				$wheres = InvestigationSubjectWheres::forCoreResults();
				break;
			default:
				$wheres = InvestigationSubjectWheres::impossible();
				break;
		}

		$loader = new LoadFileScanResultsTableData();
		$loader->custom_record_retriever_wheres = $wheres;
		return $loader->countAll();
	}

	protected function countActivityForSubject( string $subjectType, string $subjectId ) :int {
		$loader = new LoadLogs();
		$loader->wheres = InvestigationSubjectWheres::forActivitySubject(
			$subjectType,
			$subjectId,
			self::con()->db_con->activity_logs_meta->getTable()
		);
		return $loader->countAll();
	}

	private function getAssetDataBuilder() {
		if ( $this->assetDataBuilder === null ) {
			$this->assetDataBuilder = new class extends PluginThemesBase {
				public function exposeBuildPluginData( $plugin ) :array {
					return $this->buildPluginData( $plugin );
				}

				public function exposeBuildThemeData( $theme ) :array {
					return $this->buildThemeData( $theme );
				}
			};
		}
		return $this->assetDataBuilder;
	}

	private function normalizeAssetLookup( string $subjectType, string $lookup ) :string {
		try {
			$normalized = ( new InvestigationSubjectResolver() )->normalize(
				InvestigationTableContract::TABLE_TYPE_ACTIVITY,
				$subjectType,
				$lookup
			);
		}
		catch ( \Throwable $e ) {
			$normalized = [];
		}

		return (string)( $normalized[ InvestigationTableContract::REQ_KEY_SUBJECT_ID ] ?? '' );
	}
}

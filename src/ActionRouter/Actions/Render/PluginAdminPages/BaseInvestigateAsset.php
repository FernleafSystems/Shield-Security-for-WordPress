<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract,
	Actions\Investigation\InvestigationSubjectResolver,
	Actions\InvestigationTableAction,
	Exceptions\InvalidInvestigationSubjectIdentifierException,
	Exceptions\UnsupportedInvestigationSubjectTypeException,
	Exceptions\UnsupportedInvestigationTableTypeException
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\{
	ForActivityLog as InvestigationActivityTableBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationSubjectWheres;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseInvestigateAsset extends BasePluginAdminPage {

	use InvestigateAssetOptionsBuilder;
	use InvestigateCountCache;
	use InvestigateRenderContracts;
	use InvestigateStatusMapping;

	/**
	 * @phpstan-type VulnerabilityPanelContract array{
	 *   count:int,
	 *   status:string,
	 *   title:string,
	 *   summary:string,
	 *   lookup_href:string,
	 *   lookup_text:string
	 * }
	 */

	private ?InvestigateAssetDataAdapter $assetDataAdapter = null;

	protected function getLookupValue( string $queryKey ) :string {
		return $this->getTextInputFromRequestOrActionData( $queryKey );
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
				'label'     => __( 'File Scan Status', 'wp-simple-firewall' ),
				'count'     => $counts[ 'file_status' ],
				'is_active' => false,
			],
			'activity'    => [
				'pane_id'   => $idPrefix.'Activity',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-activity',
				'label'     => __( 'Activity', 'wp-simple-firewall' ),
				'count'     => $counts[ 'activity' ],
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
					'count'     => $counts[ 'vulnerabilities' ],
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
					? $tab[ 'label' ]
					: \sprintf( '%s (%d)', $tab[ 'label' ], $count ),
				'is_focus' => $tab[ 'is_active' ],
			];
		}
		return $items;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildFileStatusTableContractWithEmptyState(
		string $subjectType,
		string $subjectId,
		int $fileStatusCount,
		string $emptyText,
		string $emptyStatus = 'info'
	) :array {
		return ( new InvestigationFileStatusTableContractBuilder() )->buildWithEmptyState(
			$subjectType,
			$subjectId,
			$fileStatusCount,
			$emptyText,
			self::con()->plugin_urls->actionsQueueScans(),
			$emptyStatus
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildActivityTableContract( string $subjectType, string $subjectId ) :array {
		$tableAction = ActionData::Build( InvestigationTableAction::class );

		$activityTable = $this->buildTableContainerContract(
			__( 'Activity', 'wp-simple-firewall' ),
			'warning',
			InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			$subjectType,
			$subjectId,
			( new InvestigationActivityTableBuilder() )->setSubject( $subjectType, $subjectId )->buildRaw(),
			$tableAction
		);
		$activityTable[ 'is_flat' ] = true;
		$activityTable[ 'show_header' ] = false;
		return $this->normalizeInvestigationTableContract( $activityTable );
	}

	protected function buildPluginScanData( $plugin ) :array {
		return $this->getAssetDataAdapter()->buildPluginDataForInvestigate( $plugin );
	}

	protected function buildThemeScanData( $theme ) :array {
		return $this->getAssetDataAdapter()->buildThemeDataForInvestigate( $theme );
	}

	/**
	 * @return VulnerabilityPanelContract
	 */
	protected function buildVulnerabilityData( string $subjectId, string $lookupHref ) :array {
		try {
			$items = self::con()->comps->scans->WPV()->getResultsForDisplay()->getItemsForSlug( $subjectId );
		}
		catch ( \Exception $e ) {
			$items = [];
		}
		$count = \count( $items );
		$hasVulnerabilities = $count > 0;

		return $this->normalizeVulnerabilityPanelContract( [
			'count'       => $count,
			'status'      => $hasVulnerabilities ? 'critical' : 'good',
			'title'       => $hasVulnerabilities
				? __( 'Known Vulnerabilities', 'wp-simple-firewall' )
				: '',
			'summary'     => $hasVulnerabilities
				? \sprintf( _n( '%d vulnerability detected for this asset.', '%d vulnerabilities detected for this asset.', $count, 'wp-simple-firewall' ), $count )
				: __( 'No known vulnerabilities were detected for this asset in the current scan results.', 'wp-simple-firewall' ),
			'lookup_href' => $hasVulnerabilities ? $lookupHref : '',
			'lookup_text' => $hasVulnerabilities ? __( 'Vulnerability Lookup', 'wp-simple-firewall' ) : '',
		] );
	}

	protected function countFileScanResultsForSubject( string $subjectType, string $subjectId ) :int {
		$subjectType = \strtolower( \trim( $subjectType ) );
		return $this->cachedCount(
			'file_status',
			$subjectType,
			$subjectId,
			function () use ( $subjectType, $subjectId ) :int {
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
		);
	}

	protected function countActivityForSubject( string $subjectType, string $subjectId ) :int {
		$subjectType = \strtolower( \trim( $subjectType ) );
		return $this->cachedCount(
			'activity',
			$subjectType,
			$subjectId,
			function () use ( $subjectType, $subjectId ) :int {
				$loader = new LoadLogs();
				$loader->wheres = InvestigationSubjectWheres::forActivitySubject(
					$subjectType,
					$subjectId,
					self::con()->db_con->activity_logs_meta->getTable()
				);
				return $loader->countAll();
			}
		);
	}

	private function getAssetDataAdapter() :InvestigateAssetDataAdapter {
		if ( $this->assetDataAdapter === null ) {
			$this->assetDataAdapter = new InvestigateAssetDataAdapter();
		}
		return $this->assetDataAdapter;
	}

	/**
	 * @param array<string,mixed> $contract
	 * @return VulnerabilityPanelContract
	 */
	protected function normalizeVulnerabilityPanelContract( array $contract = [] ) :array {
		return \array_merge(
			[
				'count'       => 0,
				'status'      => 'good',
				'title'       => '',
				'summary'     => '',
				'lookup_href' => '',
				'lookup_text' => '',
			],
			$contract
		);
	}

	private function normalizeAssetLookup( string $subjectType, string $lookup ) :string {
		try {
			return ( new InvestigationSubjectResolver() )->normalize(
				InvestigationTableContract::TABLE_TYPE_ACTIVITY,
				$subjectType,
				$lookup
			)[ InvestigationTableContract::REQ_KEY_SUBJECT_ID ];
		}
		catch ( InvalidInvestigationSubjectIdentifierException|UnsupportedInvestigationSubjectTypeException|UnsupportedInvestigationTableTypeException $e ) {
			return '';
		}
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;

abstract class BaseInvestigateByAssetSubject extends BaseInvestigateAsset {

	protected function getRenderData() :array {
		$con = self::con();
		$strings = $this->getPageStrings();
		$lookup = $this->getLookupValue( $this->getLookupQueryKey() );
		$lookupAjax = $this->buildLookupAjaxPayload();

		$hasLookup = !empty( $lookup );
		$hasAllSubjects = $this->isAggregateLookupValue( $lookup );
		$subject = $hasAllSubjects ? null : $this->resolveSubject( $lookup );
		$hasSubject = !$hasAllSubjects && !empty( $subject );
		$subjectNotFound = $hasLookup && !$hasAllSubjects && !$hasSubject;

		$tabs = [];
		$railNavItems = [];
		$tables = [];
		$overviewRows = [];
		$vulnerabilities = $this->normalizeVulnerabilityPanelContract();
		$vulnerabilityPane = [];
		$subjectHeader = [];

		if ( $hasAllSubjects ) {
			$aggregateRenderContracts = $this->buildAggregateRenderContracts();
			$tabs = $aggregateRenderContracts[ 'tabs' ];
			$railNavItems = $aggregateRenderContracts[ 'rail_nav_items' ];
			$tables = $aggregateRenderContracts[ 'tables' ];
			$subjectHeader = $aggregateRenderContracts[ 'subject_header' ];
			$vulnerabilityPane = $aggregateRenderContracts[ 'vulnerability_pane' ];
		}
		elseif ( $hasSubject ) {
			$assetData = $this->buildSubjectAssetData( $subject );
			$subjectId = $this->extractAssetSubjectId( $assetData );
			$subjectTitle = $this->extractAssetSubjectTitle( $assetData );
			$subjectType = $this->getSubjectType();

			$fileStatusCount = $this->countFileScanResultsForSubject( $subjectType, $subjectId );
			$activityCount = $this->countActivityForSubject( $subjectType, $subjectId );
			$vulnerabilities = $this->buildVulnerabilityData( $subjectId, $assetData[ 'hrefs' ][ 'vul_info' ] );

			$tabs = $this->buildAssetTabsPayload( $subjectType, [
				'file_status'     => $fileStatusCount,
				'vulnerabilities' => $vulnerabilities[ 'count' ],
				'activity'        => $activityCount,
			], true );
			$railNavItems = $this->buildRailNavItemsFromTabs( $tabs );
			$tables = [
				'file_status' => $this->buildFileStatusTableContractWithEmptyState(
					$subjectType,
					$subjectId,
					$fileStatusCount,
					$strings[ 'file_status_empty_text' ]
				),
				'activity'    => $this->withEmptyStateTableContract(
					$this->buildActivityTableContract( $subjectType, $subjectId ),
					$activityCount,
					$strings[ 'activity_empty_text' ]
				),
			];
			$overviewRows = $this->buildOverviewRows( $assetData, $vulnerabilities );
			$subjectHeader = $this->buildSubjectHeaderContract(
				$subjectTitle,
				(string)( $assetData[ 'info' ][ 'version' ] ?? '' ),
				$this->buildSubjectContextStepJson( $subjectId, $subjectTitle )
			);
		}

		return [
			'flags'   => [
				'has_lookup'        => $hasLookup,
				'has_all_subjects'  => $hasAllSubjects,
				'has_subject'       => $hasSubject,
				'subject_not_found' => $subjectNotFound,
			],
			'hrefs'   => [
				$this->getLookupHrefKey() => $this->getLookupHref(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( $this->getSubjectAvatarIcon() ),
			],
			'strings' => $strings,
			'vars'    => [
				$this->getLookupQueryKey()      => $lookup,
				$this->getLookupOptionsVarKey() => $this->buildLookupOptionsPayload(),
				'lookup_route'                  => $this->buildLookupRouteContract( $this->getLookupSubNav() ),
				'lookup_behavior'               => $this->buildLookupBehaviorContract( true, true, true ),
				'lookup_field'                  => $this->buildLookupFieldContract( $this->getLookupSubjectKey(), $this->getLookupQueryKey() ),
				'lookup_ajax'                   => $lookupAjax,
				'lookup_ajax_attr'              => $this->buildLookupAjaxAttrValue( $lookupAjax ),
				'lookup_shortcuts'              => [],
				'offcanvas_history_mode'        => '',
				'subject_header'                => $subjectHeader,
				'tabs'                          => $tabs,
				'rail_nav_items'                => $railNavItems,
				'tables'                        => $tables,
				'overview_rows'                 => $overviewRows,
				'vulnerabilities'               => $vulnerabilities,
				'vulnerability_pane'            => $vulnerabilityPane,
			],
		];
	}

	/**
	 * @return array{
	 *   tabs:array<string,array<string,mixed>>,
	 *   rail_nav_items:list<array<string,mixed>>,
	 *   tables:array{activity:array<string,mixed>},
	 *   subject_header:array<string,string>,
	 *   vulnerability_pane:array{tab:array<string,mixed>,strings:array<string,string>}
	 * }
	 */
	protected function buildAggregateRenderContracts() :array {
		$subjectType = $this->getAggregateSubjectType();
		$subjectId = InvestigationTableContract::SUBJECT_ID_ALL;
		$vulnerabilities = $this->buildAggregateVulnerabilitiesPayload();
		$activityCount = $this->countActivityForSubject( $subjectType, $subjectId );

		$tabs = $this->buildAggregateAssetTabsPayload(
			$subjectType,
			[
				'vulnerabilities' => $this->extractKnownVulnerabilityCount( $vulnerabilities ),
				'activity'        => $activityCount,
			]
		);

		return [
			'tabs'               => $tabs,
			'rail_nav_items'     => $this->buildRailNavItemsFromTabs( $tabs ),
			'tables'             => [
				'activity' => $this->withEmptyStateTableContract(
					$this->buildActivityTableContract( $subjectType, $subjectId ),
					$activityCount,
					$this->getAggregateActivityEmptyText()
				),
			],
			'subject_header'     => $this->buildSubjectHeaderContract(
				$this->getAggregateTitle(),
				$this->getAggregateMeta()
			),
			'vulnerability_pane' => $this->buildAggregateVulnerabilityPane( $tabs, $vulnerabilities ),
		];
	}

	protected function buildAggregateVulnerabilitiesPayload() :array {
		return ( new ScansVulnerabilitiesBuilder() )->buildForAssetType( $this->getAggregateAssetType() );
	}

	protected function buildAggregateVulnerabilityPane( array $tabs, array $vulnerabilities ) :array {
		$tab = ( new ScansResultsViewBuilder() )->buildRailPaneData(
			'vulnerabilities',
			$vulnerabilities,
			'vulnerable'
		);
		$tab[ 'pane_id' ] = $tabs[ 'vulnerabilities' ][ 'pane_id' ];

		return [
			'tab'     => $tab,
			'strings' => [
				'no_issues'    => $this->getAggregateVulnerabilityNoIssuesText(),
				'pane_loading' => __( 'Loading scan details...', 'wp-simple-firewall' ),
			],
		];
	}

	protected function buildAggregateAssetTabsPayload( string $subjectKey, array $counts ) :array {
		$subjectKey = \strtolower( \trim( $subjectKey ) );
		$idPrefix = 'tabInvestigate'.\str_replace( ' ', '', \ucwords( \str_replace( '_', ' ', $subjectKey ) ) );

		$tabs = [
			'vulnerabilities' => [
				'pane_id'   => $idPrefix.'Vulnerabilities',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-vulnerabilities',
				'label'     => __( 'Known Vulnerabilities', 'wp-simple-firewall' ),
				'count'     => (int)$counts[ 'vulnerabilities' ],
				'is_active' => true,
			],
			'activity'        => [
				'pane_id'   => $idPrefix.'Activity',
				'nav_id'    => 'tab-navlink-'.$subjectKey.'-activity',
				'label'     => __( 'Activity', 'wp-simple-firewall' ),
				'count'     => (int)$counts[ 'activity' ],
				'is_active' => false,
			],
		];

		foreach ( $tabs as $key => $tab ) {
			$tabs[ $key ][ 'target' ] = '#'.$tab[ 'pane_id' ];
			$tabs[ $key ][ 'controls' ] = $tab[ 'pane_id' ];
		}

		return $tabs;
	}

	private function extractKnownVulnerabilityCount( array $vulnerabilities ) :int {
		return (int)$vulnerabilities[ 'sections' ][ 'vulnerable' ][ 'count' ];
	}

	protected function buildLookupOptionsPayload() :array {
		return $this->buildLookupOptions();
	}

	protected function isAggregateLookupValue( string $lookup ) :bool {
		return $lookup !== '' && \hash_equals( $this->getAggregateLookupValue(), $lookup );
	}

	protected function buildLookupAjaxPayload() :array {
		return $this->buildLookupAjaxContract( $this->getLookupSubjectKey() );
	}

	protected function buildOverviewRows( array $assetData, array $vulnerabilities ) :array {
		return ( new InvestigateOverviewRowsBuilder() )->forAsset(
			$assetData,
			$vulnerabilities,
			$this->getSubjectType(),
			$this->getAssetIdentifierLabel()
		);
	}

	protected function extractAssetSubjectId( array $assetData ) :string {
		return $assetData[ 'info' ][ 'file' ];
	}

	protected function extractAssetSubjectTitle( array $assetData ) :string {
		return (string)( $assetData[ 'info' ][ 'name' ] ?? '' );
	}

	protected function buildSubjectContextStepJson( string $subjectId, string $subjectTitle ) :string {
		return '';
	}

	abstract protected function getSubjectType() :string;

	abstract protected function getAggregateLookupValue() :string;

	abstract protected function getAggregateTitle() :string;

	abstract protected function getAggregateMeta() :string;

	abstract protected function getAggregateAssetType() :string;

	abstract protected function getAggregateSubjectType() :string;

	abstract protected function getAggregateVulnerabilityNoIssuesText() :string;

	abstract protected function getAggregateActivityEmptyText() :string;

	abstract protected function getLookupQueryKey() :string;

	abstract protected function getLookupOptionsVarKey() :string;

	abstract protected function getLookupHrefKey() :string;

	abstract protected function getLookupHref() :string;

	abstract protected function getLookupSubNav() :string;

	abstract protected function getLookupSubjectKey() :string;

	abstract protected function getSubjectAvatarIcon() :string;

	abstract protected function getAssetIdentifierLabel() :string;

	abstract protected function getPageStrings() :array;

	abstract protected function buildLookupOptions() :array;

	abstract protected function resolveSubject( string $lookup );

	abstract protected function buildSubjectAssetData( $subject ) :array;
}

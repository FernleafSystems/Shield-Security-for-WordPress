<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class BaseInvestigateByAssetSubject extends BaseInvestigateAsset {

	protected function getRenderData() :array {
		$con = self::con();
		$strings = $this->getPageStrings();
		$lookup = $this->getLookupValue( $this->getLookupQueryKey() );
		$subject = $this->resolveSubject( $lookup );

		$hasLookup = !empty( $lookup );
		$hasSubject = !empty( $subject );
		$subjectNotFound = $hasLookup && !$hasSubject;

		$tabs = [];
		$railNavItems = [];
		$tables = [];
		$overviewRows = [];
		$vulnerabilities = [];

		if ( $hasSubject ) {
			$assetData = $this->buildSubjectAssetData( $subject );
			$subjectId = $this->extractAssetSubjectId( $assetData );
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
			$tables = $this->buildAssetTables( $subjectType, $subjectId, $subjectId );
			$tables[ 'file_status' ] = $this->withEmptyStateTableContract(
				$tables[ 'file_status' ],
				$fileStatusCount,
				$strings[ 'file_status_empty_text' ]
			);
			$tables[ 'activity' ] = $this->withEmptyStateTableContract(
				$tables[ 'activity' ],
				$activityCount,
				$strings[ 'activity_empty_text' ]
			);
			$overviewRows = $this->buildOverviewRows( $assetData, $vulnerabilities );
		}

		return [
			'flags'   => [
				'has_lookup'        => $hasLookup,
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
				$this->getLookupOptionsVarKey() => $this->buildLookupOptions(),
				'lookup_route'                  => $this->buildLookupRouteContract( $this->getLookupSubNav() ),
				'tabs'                          => $tabs,
				'rail_nav_items'                => $railNavItems,
				'tables'                        => $tables,
				'overview_rows'                 => $overviewRows,
				'vulnerabilities'               => $vulnerabilities,
			],
		];
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

	abstract protected function getSubjectType() :string;

	abstract protected function getLookupQueryKey() :string;

	abstract protected function getLookupOptionsVarKey() :string;

	abstract protected function getLookupHrefKey() :string;

	abstract protected function getLookupHref() :string;

	abstract protected function getLookupSubNav() :string;

	abstract protected function getSubjectAvatarIcon() :string;

	abstract protected function getAssetIdentifierLabel() :string;

	abstract protected function getPageStrings() :array;

	abstract protected function buildLookupOptions() :array;

	abstract protected function resolveSubject( string $lookup );

	abstract protected function buildSubjectAssetData( $subject ) :array;
}

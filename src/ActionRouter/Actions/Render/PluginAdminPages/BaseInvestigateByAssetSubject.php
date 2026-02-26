<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

abstract class BaseInvestigateByAssetSubject extends BaseInvestigateAsset {

	protected function getRenderData() :array {
		$con = self::con();
		$strings = $this->getPageStrings();
		$lookup = $this->getLookupValue( $this->getLookupQueryKey() );
		$subject = $this->resolveSubject( $lookup );

		$hasLookup = !empty( $lookup );
		$hasSubject = !empty( $subject );
		$subjectNotFound = $hasLookup && !$hasSubject;

		$subjectData = [];
		$summary = [];
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
			$vulnerabilities = $this->buildVulnerabilityData( $subjectId, (string)( $assetData[ 'hrefs' ][ 'vul_info' ] ?? '' ) );

			$summary = [
				'vulnerabilities' => [
					'label'  => __( 'Vulnerabilities', 'wp-simple-firewall' ),
					'count'  => (int)$vulnerabilities[ 'count' ],
					'status' => (string)$vulnerabilities[ 'status' ],
				],
				'file_status'     => [
					'label'  => __( 'File Issues', 'wp-simple-firewall' ),
					'count'  => $fileStatusCount,
					'status' => $this->mapCountToStatus( $fileStatusCount, 'good' ),
				],
				'activity'        => [
					'label'  => __( 'Activity', 'wp-simple-firewall' ),
					'count'  => $activityCount,
					'status' => $this->mapCountToStatus( $activityCount ),
				],
				'issues'          => [
					'label'  => __( 'Total Findings', 'wp-simple-firewall' ),
					'count'  => (int)( $assetData[ 'vars' ][ 'count_items' ] ?? 0 ),
					'status' => $this->mapCountToStatus( (int)( $assetData[ 'vars' ][ 'count_items' ] ?? 0 ), 'good' ),
				],
			];

			$tabs = $this->buildAssetTabsPayload( $subjectType, [
				'file_status'     => $fileStatusCount,
				'vulnerabilities' => (int)$vulnerabilities[ 'count' ],
				'activity'        => $activityCount,
			], true );
			$railNavItems = $this->buildRailNavItemsFromTabs( $tabs );
			$tables = $this->buildAssetTables( $subjectType, $subjectId, $subjectId );
			$tables[ 'file_status' ] = $this->withEmptyStateTableContract(
				$tables[ 'file_status' ],
				$fileStatusCount,
				(string)( $strings[ 'file_status_empty_text' ] ?? __( 'No file status records were found for this subject.', 'wp-simple-firewall' ) )
			);
			$tables[ 'activity' ] = $this->withEmptyStateTableContract(
				$tables[ 'activity' ],
				$activityCount,
				(string)( $strings[ 'activity_empty_text' ] ?? __( 'No activity records were found for this subject.', 'wp-simple-firewall' ) )
			);
			$subjectData = $this->buildSubjectHeaderData( $assetData );
			$overviewRows = $this->buildOverviewRows( $assetData );
		}

		return [
			'flags'   => [
				'has_lookup'        => $hasLookup,
				'has_subject'       => $hasSubject,
				'subject_not_found' => $subjectNotFound,
			],
			'hrefs'   => [
				'back_to_investigate'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
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
				'subject'                       => $subjectData,
				'summary'                       => $summary,
				'tabs'                          => $tabs,
				'rail_nav_items'                => $railNavItems,
				'tables'                        => $tables,
				'overview_rows'                 => $overviewRows,
				'vulnerabilities'               => $vulnerabilities,
			],
		];
	}

	protected function buildOverviewRows( array $assetData ) :array {
		$info = $assetData[ 'info' ] ?? [];
		$author = (string)( $info[ 'author' ] ?? '' );
		$authorUrl = (string)( $info[ 'author_url' ] ?? '' );

		return [
			[
				'label' => __( 'Name', 'wp-simple-firewall' ),
				'value' => (string)( $info[ 'name' ] ?? '' ),
			],
			[
				'label' => __( 'Slug', 'wp-simple-firewall' ),
				'value' => (string)( $info[ 'slug' ] ?? '' ),
			],
			[
				'label' => __( 'Version', 'wp-simple-firewall' ),
				'value' => (string)( $info[ 'version' ] ?? '' ),
			],
			[
				'label'      => __( 'Author', 'wp-simple-firewall' ),
				'value'      => $author,
				'value_href' => $authorUrl,
			],
			[
				'label' => $this->getAssetIdentifierLabel(),
				'value' => (string)( $info[ 'file' ] ?? '' ),
			],
			[
				'label' => __( 'Install Directory', 'wp-simple-firewall' ),
				'value' => (string)( $info[ 'dir' ] ?? '' ),
			],
			[
				'label' => __( 'Installed', 'wp-simple-firewall' ),
				'value' => (string)( $info[ 'installed_at' ] ?? '' ),
			],
		];
	}

	protected function buildSubjectHeaderData( array $assetData ) :array {
		$info = $assetData[ 'info' ] ?? [];
		$flags = $assetData[ 'flags' ] ?? [];

		$status = $this->mapFlagGroupToStatus( [
			!empty( $flags[ 'is_vulnerable' ] ) ? 'critical' : '',
			( !empty( $flags[ 'has_guard_files' ] ) || !empty( $flags[ 'is_abandoned' ] ) || !empty( $flags[ 'has_update' ] ) )
				? 'warning'
				: '',
		], 'good' );

		$pills = [
			[
				'status' => $this->mapCountToStatus( !empty( $flags[ 'is_active' ] ) ? 1 : 0, 'warning', 'good' ),
				'label'  => !empty( $flags[ 'is_active' ] )
					? __( 'Active', 'wp-simple-firewall' )
					: __( 'Inactive', 'wp-simple-firewall' ),
			]
		];

		if ( !empty( $flags[ 'has_update' ] ) ) {
			$pills[] = [
				'status' => 'warning',
				'label'  => __( 'Update Available', 'wp-simple-firewall' ),
			];
		}

		if ( !empty( $flags[ 'is_vulnerable' ] ) ) {
			$pills[] = [
				'status' => 'critical',
				'label'  => __( 'Vulnerable', 'wp-simple-firewall' ),
			];
		}

		$pills = \array_merge( $pills, $this->getExtraStatusPills( $flags ) );

		return [
			'status'       => $status,
			'title'        => (string)( $info[ 'name' ] ?? '' ),
			'avatar_icon'  => self::con()->svgs->iconClass( $this->getSubjectAvatarIcon() ),
			'meta'         => [
				[
					'label' => __( 'Version', 'wp-simple-firewall' ),
					'value' => (string)( $info[ 'version' ] ?? '' ),
				],
				[
					'label' => __( 'Author', 'wp-simple-firewall' ),
					'value' => (string)( $info[ 'author' ] ?? '' ),
				],
				[
					'label' => $this->getAssetIdentifierLabel(),
					'value' => (string)( $info[ 'file' ] ?? '' ),
				],
			],
			'status_pills' => $pills,
		];
	}

	protected function getExtraStatusPills( array $assetFlags ) :array {
		return [];
	}

	protected function extractAssetSubjectId( array $assetData ) :string {
		return (string)( $assetData[ 'info' ][ 'file' ] ?? '' );
	}

	abstract protected function getSubjectType() :string;

	abstract protected function getLookupQueryKey() :string;

	abstract protected function getLookupOptionsVarKey() :string;

	abstract protected function getLookupHrefKey() :string;

	abstract protected function getLookupHref() :string;

	abstract protected function getLookupSubNav() :string;

	abstract protected function getSubjectAvatarIcon() :string;

	abstract protected function getAssetIdentifierLabel() :string;

	abstract protected function getChangeLookupText() :string;

	abstract protected function getPageStrings() :array;

	abstract protected function buildLookupOptions() :array;

	abstract protected function resolveSubject( string $lookup );

	abstract protected function buildSubjectAssetData( $subject ) :array;
}

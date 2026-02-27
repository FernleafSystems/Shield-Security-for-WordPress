<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;

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
			$vulnerabilities = $this->buildVulnerabilityData( $subjectId, (string)( $assetData[ 'hrefs' ][ 'vul_info' ] ?? '' ) );

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
				(string)( $strings[ 'file_status_empty_text' ] ?? __( 'No file scan status records were found for this subject.', 'wp-simple-firewall' ) )
			);
			$tables[ 'activity' ] = $this->withEmptyStateTableContract(
				$tables[ 'activity' ],
				$activityCount,
				(string)( $strings[ 'activity_empty_text' ] ?? __( 'No activity records were found for this subject.', 'wp-simple-firewall' ) )
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
		$info = $assetData[ 'info' ] ?? [];
		$flags = $assetData[ 'flags' ] ?? [];
		$author = (string)( $info[ 'author' ] ?? '' );
		$authorUrl = (string)( $info[ 'author_url' ] ?? '' );

		$rows = [
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
			[
				'label' => __( 'Active Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'is_active' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			],
		];

		if ( $this->getSubjectType() === InvestigationTableContract::SUBJECT_TYPE_PLUGIN ) {
			$rows[] = [
				'label' => __( 'Update Available Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'has_update' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
			$rows[] = [
				'label' => __( 'Vulnerability Status', 'wp-simple-firewall' ),
				'value' => ( (int)( $vulnerabilities[ 'count' ] ?? 0 ) > 0 )
					? __( 'Known Vulnerabilities', 'wp-simple-firewall' )
					: __( 'No Known Vulnerabilities', 'wp-simple-firewall' ),
			];
		}
		elseif ( $this->getSubjectType() === InvestigationTableContract::SUBJECT_TYPE_THEME ) {
			$rows[] = [
				'label' => __( 'Child Theme Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'is_child' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
		}

		return $rows;
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

	abstract protected function getPageStrings() :array;

	abstract protected function buildLookupOptions() :array;

	abstract protected function resolveSubject( string $lookup );

	abstract protected function buildSubjectAssetData( $subject ) :array;
}

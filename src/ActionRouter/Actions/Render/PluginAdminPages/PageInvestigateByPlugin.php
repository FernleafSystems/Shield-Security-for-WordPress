<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateByPlugin extends BaseInvestigateAsset {

	public const SLUG = 'plugin_admin_page_investigate_by_plugin';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_plugin.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = $this->getLookupValue( 'plugin_slug' );
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
		$pluginOptions = $this->buildPluginLookupOptions();

		if ( $hasSubject ) {
			$assetData = $this->buildSubjectAssetData( $subject );
			$subjectId = (string)( $assetData[ 'info' ][ 'file' ] ?? '' );

			$fileStatusCount = $this->countFileScanResultsForSubject( InvestigationTableContract::SUBJECT_TYPE_PLUGIN, $subjectId );
			$activityCount = $this->countActivityForSubject( InvestigationTableContract::SUBJECT_TYPE_PLUGIN, $subjectId );
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
					'status' => $fileStatusCount > 0 ? 'warning' : 'good',
				],
				'activity'        => [
					'label'  => __( 'Activity', 'wp-simple-firewall' ),
					'count'  => $activityCount,
					'status' => $activityCount > 0 ? 'warning' : 'info',
				],
				'issues'          => [
					'label'  => __( 'Total Findings', 'wp-simple-firewall' ),
					'count'  => (int)( $assetData[ 'vars' ][ 'count_items' ] ?? 0 ),
					'status' => (int)( $assetData[ 'vars' ][ 'count_items' ] ?? 0 ) > 0 ? 'warning' : 'good',
				],
			];

			$tabs = $this->buildAssetTabsPayload( InvestigationTableContract::SUBJECT_TYPE_PLUGIN, [
				'file_status'     => $fileStatusCount,
				'vulnerabilities' => (int)$vulnerabilities[ 'count' ],
				'activity'        => $activityCount,
			], true );
			$railNavItems = $this->buildRailNavItemsFromTabs( $tabs );
			$tables = $this->buildAssetTables(
				InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
				$subjectId,
				$subjectId
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
				'back_to_investigate' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
				'by_plugin'           => $con->plugin_urls->investigateByPlugin(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'puzzle-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate By Plugin', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Inspect plugin integrity, vulnerability status, and activity footprint.', 'wp-simple-firewall' ),
				'lookup_label'        => __( 'Plugin Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder'  => __( 'Select a plugin', 'wp-simple-firewall' ),
				'lookup_submit'       => __( 'Load Plugin Context', 'wp-simple-firewall' ),
				'back_to_investigate' => __( 'Back To Investigate', 'wp-simple-firewall' ),
				'no_subject_title'    => __( 'No Plugin Selected', 'wp-simple-firewall' ),
				'no_subject_text'     => __( 'Select a plugin to load file status and activity context.', 'wp-simple-firewall' ),
				'not_found_title'     => __( 'Plugin Not Found', 'wp-simple-firewall' ),
				'not_found_text'      => __( 'The selected plugin isn\'t currently installed on this site.', 'wp-simple-firewall' ),
				'overview_title'      => __( 'Plugin Overview', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'plugin_slug'   => $lookup,
				'plugin_options'=> $pluginOptions,
				'lookup_route'  => $this->buildLookupRouteContract( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ),
				'subject'       => $subjectData,
				'summary'       => $summary,
				'tabs'          => $tabs,
				'rail_nav_items'=> $railNavItems,
				'tables'        => $tables,
				'overview_rows' => $overviewRows,
				'vulnerabilities' => $vulnerabilities,
			],
		];
	}

	protected function resolveSubject( string $lookup ) {
		return $this->resolvePluginByLookup( $lookup );
	}

	protected function buildSubjectAssetData( $subject ) :array {
		return $this->buildPluginScanData( $subject );
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
				'label' => __( 'File', 'wp-simple-firewall' ),
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

		$status = 'good';
		if ( !empty( $flags[ 'is_vulnerable' ] ) ) {
			$status = 'critical';
		}
		elseif ( !empty( $flags[ 'has_guard_files' ] ) || !empty( $flags[ 'is_abandoned' ] ) || !empty( $flags[ 'has_update' ] ) ) {
			$status = 'warning';
		}

		$pills = [];
		$pills[] = [
			'status' => !empty( $flags[ 'is_active' ] ) ? 'good' : 'warning',
			'label'  => !empty( $flags[ 'is_active' ] )
				? __( 'Active', 'wp-simple-firewall' )
				: __( 'Inactive', 'wp-simple-firewall' ),
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

		return [
			'status'       => $status,
			'title'        => (string)( $info[ 'name' ] ?? '' ),
			'avatar_icon'  => self::con()->svgs->iconClass( 'puzzle-fill' ),
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
					'label' => __( 'File', 'wp-simple-firewall' ),
					'value' => (string)( $info[ 'file' ] ?? '' ),
				],
			],
			'status_pills' => $pills,
			'change_href'  => self::con()->plugin_urls->investigateByPlugin(),
			'change_text'  => __( 'Change Plugin', 'wp-simple-firewall' ),
		];
	}
}

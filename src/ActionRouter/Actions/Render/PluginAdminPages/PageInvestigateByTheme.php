<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateByTheme extends BaseInvestigateByAssetSubject {

	public const SLUG = 'plugin_admin_page_investigate_by_theme';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_theme.twig';
	public const LOOKUP_ALL_THEMES = '__shield_all_themes__';

	protected function resolveSubject( string $lookup ) {
		return $this->resolveThemeByLookup( $lookup );
	}

	protected function buildSubjectAssetData( $subject ) :array {
		return $this->buildThemeScanData( $subject );
	}

	protected function getSubjectType() :string {
		return InvestigationTableContract::SUBJECT_TYPE_THEME;
	}

	protected function getAggregateLookupValue() :string {
		return self::LOOKUP_ALL_THEMES;
	}

	protected function getAggregateTitle() :string {
		return __( 'All Themes', 'wp-simple-firewall' );
	}

	protected function getAggregateMeta() :string {
		return __( 'Installed theme events', 'wp-simple-firewall' );
	}

	protected function getAggregateAssetType() :string {
		return InvestigationTableContract::SUBJECT_TYPE_THEME;
	}

	protected function getAggregateSubjectType() :string {
		return InvestigationTableContract::SUBJECT_TYPE_ALL_THEMES;
	}

	protected function getAggregateVulnerabilityNoIssuesText() :string {
		return __( "Previous scans didn't detect any vulnerable themes.", 'wp-simple-firewall' );
	}

	protected function getAggregateActivityEmptyText() :string {
		return __( 'No theme activity records were found.', 'wp-simple-firewall' );
	}

	protected function getLookupQueryKey() :string {
		return 'theme_slug';
	}

	protected function getLookupOptionsVarKey() :string {
		return 'theme_options';
	}

	protected function getLookupHrefKey() :string {
		return 'by_theme';
	}

	protected function getLookupHref() :string {
		return self::con()->plugin_urls->investigateByTheme();
	}

	protected function getLookupSubNav() :string {
		return PluginNavs::SUBNAV_ACTIVITY_BY_THEME;
	}

	protected function getLookupSubjectKey() :string {
		return 'theme';
	}

	protected function getSubjectAvatarIcon() :string {
		return 'palette-fill';
	}

	protected function getAssetIdentifierLabel() :string {
		return __( 'Stylesheet', 'wp-simple-firewall' );
	}

	protected function getPageStrings() :array {
		return [
			'inner_page_title'    => __( 'Investigate By Theme', 'wp-simple-firewall' ),
			'inner_page_subtitle' => __( 'Inspect theme integrity, vulnerability status, and activity footprint.', 'wp-simple-firewall' ),
			'lookup_label'        => __( 'Theme Lookup', 'wp-simple-firewall' ),
			'lookup_placeholder'  => __( 'Search for a theme...', 'wp-simple-firewall' ),
			'lookup_submit'       => __( 'Load Theme Context', 'wp-simple-firewall' ),
			'lookup_helper'       => __( 'Type at least 2 characters to search installed themes.', 'wp-simple-firewall' ),
			'change_subject'      => __( 'Change theme', 'wp-simple-firewall' ),
			'not_found_title'     => __( 'Theme Not Found', 'wp-simple-firewall' ),
			'not_found_text'      => __( 'The selected theme isn\'t currently installed on this site.', 'wp-simple-firewall' ),
			'overview_title'      => __( 'Theme Overview', 'wp-simple-firewall' ),
			'file_status_empty_text' => __( 'No file scan status records were found for this subject.', 'wp-simple-firewall' ),
			'activity_empty_text'    => __( 'No activity records were found for this subject.', 'wp-simple-firewall' ),
		];
	}

	protected function buildLookupOptions() :array {
		return \array_merge(
			[
				[
					'value' => self::LOOKUP_ALL_THEMES,
					'label' => __( 'All Themes', 'wp-simple-firewall' ),
				],
			],
			$this->buildThemeLookupOptions()
		);
	}

	protected function buildLookupAjaxPayload() :array {
		return [];
	}

	protected function buildSubjectContextStepJson( string $subjectId, string $subjectTitle ) :string {
		if ( $subjectId === '' ) {
			return '';
		}

		$definition = PluginNavs::investigateLandingSubjectDefinitions()[ 'theme' ];
		$title = \trim( $subjectTitle );

		return OperatorChromeContract::encodeJson( OperatorChromeContract::normalizeStep( [
			'breadcrumb_label' => $title !== '' ? $title : $definition[ 'label' ],
			'title'            => $title !== '' ? $title : $definition[ 'label' ],
			'summary'          => $definition[ 'context_summary' ],
			'focus'            => $definition[ 'context_focus' ],
			'next_step'        => $definition[ 'context_next_step' ],
			'icon_class'       => $definition[ 'icon_class' ],
			'badge'            => $definition[ 'context_badge' ],
			'badge_status'     => $definition[ 'status' ],
			'color_key'        => 'investigate',
			'actions'          => ( new ThemeReinstallContextActionBuilder() )->buildForThemeStylesheet( $subjectId, $title ),
		] ) );
	}

}

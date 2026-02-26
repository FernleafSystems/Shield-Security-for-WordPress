<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateByTheme extends BaseInvestigateByAssetSubject {

	public const SLUG = 'plugin_admin_page_investigate_by_theme';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_theme.twig';

	protected function resolveSubject( string $lookup ) {
		return $this->resolveThemeByLookup( $lookup );
	}

	protected function buildSubjectAssetData( $subject ) :array {
		return $this->buildThemeScanData( $subject );
	}

	protected function getSubjectType() :string {
		return InvestigationTableContract::SUBJECT_TYPE_THEME;
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

	protected function getSubjectAvatarIcon() :string {
		return 'palette-fill';
	}

	protected function getAssetIdentifierLabel() :string {
		return __( 'Stylesheet', 'wp-simple-firewall' );
	}

	protected function getChangeLookupText() :string {
		return __( 'Change Theme', 'wp-simple-firewall' );
	}

	protected function getPageStrings() :array {
		return [
			'inner_page_title'    => __( 'Investigate By Theme', 'wp-simple-firewall' ),
			'inner_page_subtitle' => __( 'Inspect theme integrity, vulnerability status, and activity footprint.', 'wp-simple-firewall' ),
			'lookup_label'        => __( 'Theme Lookup', 'wp-simple-firewall' ),
			'lookup_placeholder'  => __( 'Select a theme', 'wp-simple-firewall' ),
			'lookup_submit'       => __( 'Load Theme Context', 'wp-simple-firewall' ),
			'back_to_investigate' => __( 'Back To Investigate', 'wp-simple-firewall' ),
			'no_subject_title'    => __( 'No Theme Selected', 'wp-simple-firewall' ),
			'no_subject_text'     => __( 'Select a theme to load file status and activity context.', 'wp-simple-firewall' ),
			'not_found_title'     => __( 'Theme Not Found', 'wp-simple-firewall' ),
			'not_found_text'      => __( 'The selected theme isn\'t currently installed on this site.', 'wp-simple-firewall' ),
			'overview_title'      => __( 'Theme Overview', 'wp-simple-firewall' ),
			'file_status_empty_text' => __( 'No file status records were found for this subject.', 'wp-simple-firewall' ),
			'activity_empty_text'    => __( 'No activity records were found for this subject.', 'wp-simple-firewall' ),
		];
	}

	protected function buildLookupOptions() :array {
		return $this->buildThemeLookupOptions();
	}

	protected function getExtraStatusPills( array $assetFlags ) :array {
		if ( empty( $assetFlags[ 'is_child' ] ) ) {
			return [];
		}

		return [
			[
				'status' => 'info',
				'label'  => __( 'Child Theme', 'wp-simple-firewall' ),
			],
		];
	}
}

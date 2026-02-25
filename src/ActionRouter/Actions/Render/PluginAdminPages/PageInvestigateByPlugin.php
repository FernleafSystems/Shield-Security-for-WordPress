<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateByPlugin extends BaseInvestigateByAssetSubject {

	public const SLUG = 'plugin_admin_page_investigate_by_plugin';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_plugin.twig';

	protected function resolveSubject( string $lookup ) {
		return $this->resolvePluginByLookup( $lookup );
	}

	protected function buildSubjectAssetData( $subject ) :array {
		return $this->buildPluginScanData( $subject );
	}

	protected function getSubjectType() :string {
		return InvestigationTableContract::SUBJECT_TYPE_PLUGIN;
	}

	protected function getLookupQueryKey() :string {
		return 'plugin_slug';
	}

	protected function getLookupOptionsVarKey() :string {
		return 'plugin_options';
	}

	protected function getLookupHrefKey() :string {
		return 'by_plugin';
	}

	protected function getLookupHref() :string {
		return self::con()->plugin_urls->investigateByPlugin();
	}

	protected function getLookupSubNav() :string {
		return PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN;
	}

	protected function getSubjectAvatarIcon() :string {
		return 'puzzle-fill';
	}

	protected function getAssetIdentifierLabel() :string {
		return __( 'File', 'wp-simple-firewall' );
	}

	protected function getChangeLookupText() :string {
		return __( 'Change Plugin', 'wp-simple-firewall' );
	}

	protected function getPageStrings() :array {
		return [
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
		];
	}

	protected function buildLookupOptions() :array {
		return $this->buildPluginLookupOptions();
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';

	/**
	 * @var array{
	 *   activity_log:string,
	 *   traffic_log:string,
	 *   by_user:string,
	 *   by_ip:string,
	 *   by_plugin:string,
	 *   by_theme:string,
	 *   by_core:string
	 * }|null
	 */
	private ?array $landingHrefsCache = null;

	/**
	 * @var array<string,array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   icon_class:string,
	 *   panel_type:string,
	 *   subnav_hint:string|null,
	 *   href_key:string,
	 *   input_key:string|null,
	 *   options_key:string|null,
	 *   panel_title:string,
	 *   lookup_placeholder:string,
	 *   go_label:string,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * }>|null
	 */
	private ?array $subjectDefinitionsCache = null;

	/**
	 * @var list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   href:string,
	 *   icon_class:string,
	 *   subject_label:string,
	 *   subject_description:string
	 * }>|null
	 */
	private ?array $subjectsPayloadCache = null;

	protected function getLandingTitle() :string {
		return __( 'Investigate', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Investigate user activity, request logs, and IP behavior.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'search';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_INVESTIGATE;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool
	 * }>
	 */
	protected function getLandingTiles() :array {
		return \array_map(
			fn( array $subject ) :array => [
				'key'          => $subject[ 'key' ],
				'panel_target' => $subject[ 'panel_target' ],
				'is_enabled'   => $subject[ 'is_enabled' ],
				'is_disabled'  => $subject[ 'is_disabled' ],
			],
			$this->getSubjectsPayload()
		);
	}

	protected function getLandingFlags() :array {
		return [];
	}

	protected function getLandingHrefs() :array {
		if ( $this->landingHrefsCache === null ) {
			$con = self::con();
			$this->landingHrefsCache = [
				'activity_log' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
				'traffic_log'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
				'by_user'      => $con->plugin_urls->investigateByUser(),
				'by_ip'        => $con->plugin_urls->investigateByIp(),
				'by_plugin'    => $con->plugin_urls->investigateByPlugin(),
				'by_theme'     => $con->plugin_urls->investigateByTheme(),
				'by_core'      => $con->plugin_urls->investigateByCore(),
			];
		}
		return $this->landingHrefsCache;
	}

	protected function getLandingStrings() :array {
		return [
			'label_pro' => __( 'PRO', 'wp-simple-firewall' ),
		];
	}

	protected function getLandingVars() :array {
		return [
			'subjects' => $this->getSubjectsPayload(),
		];
	}

	/**
	 * @param array{
	 *   activity_log:string,
	 *   traffic_log:string,
	 *   by_user:string,
	 *   by_ip:string,
	 *   by_plugin:string,
	 *   by_theme:string,
	 *   by_core:string
	 * } $hrefs
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   href:string,
	 *   icon_class:string,
	 *   subject_label:string,
	 *   subject_description:string
	 * }>
	 */
	private function buildSubjectsPayload( array $hrefs ) :array {
		$subjects = [];
		foreach ( $this->getSubjectDefinitions() as $subject ) {
			$hrefKey = $subject[ 'href_key' ];
			$isEnabled = $subject[ 'is_enabled' ];
			$subjects[] = [
				'key'                 => $subject[ 'key' ],
				'panel_target'        => $subject[ 'key' ],
				'is_enabled'          => $isEnabled,
				'is_disabled'         => !$isEnabled,
				'is_pro'              => $subject[ 'is_pro' ],
				'href'                => $isEnabled && $hrefKey !== '' ? $hrefs[ $hrefKey ] : '',
				'icon_class'          => $subject[ 'icon_class' ],
				'subject_label'       => $subject[ 'label' ],
				'subject_description' => $subject[ 'description' ],
			];
		}
		return $subjects;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   href:string,
	 *   icon_class:string,
	 *   subject_label:string,
	 *   subject_description:string
	 * }>
	 */
	private function getSubjectsPayload() :array {
		if ( $this->subjectsPayloadCache === null ) {
			$this->subjectsPayloadCache = $this->buildSubjectsPayload( $this->getLandingHrefs() );
		}
		return $this->subjectsPayloadCache;
	}

	/**
	 * @return array<string,array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   icon_class:string,
	 *   panel_type:string,
	 *   subnav_hint:string|null,
	 *   href_key:string,
	 *   input_key:string|null,
	 *   options_key:string|null,
	 *   panel_title:string,
	 *   lookup_placeholder:string,
	 *   go_label:string,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * }>
	 */
	protected function getSubjectDefinitions() :array {
		if ( $this->subjectDefinitionsCache === null ) {
			$this->subjectDefinitionsCache = [];
			foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $subject ) {
				$subject[ 'key' ] = $subjectKey;
				$this->subjectDefinitionsCache[ $subjectKey ] = $subject;
			}
		}
		return $this->subjectDefinitionsCache;
	}
}

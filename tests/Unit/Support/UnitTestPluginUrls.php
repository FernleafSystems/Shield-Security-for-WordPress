<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

class UnitTestPluginUrls {

	private string $rootAdminPageSlug;

	private string $adminHome;

	public function __construct(
		string $rootAdminPageSlug = 'icwp-wpsf-plugin',
		string $adminHome = '/admin/home'
	) {
		$this->rootAdminPageSlug = $rootAdminPageSlug;
		$this->adminHome = $adminHome;
	}

	public function rootAdminPageSlug() :string {
		return $this->rootAdminPageSlug;
	}

	public function adminHome() :string {
		return $this->adminHome;
	}

	public function adminTopNav( string $nav, string $subnav = '' ) :string {
		return '/admin/'.$nav.'/'.$subnav;
	}

	public function configureHome( string $zone = '' ) :string {
		$url = '/admin/zones/overview';
		return empty( $zone ) ? $url : $url.'?zone='.$zone;
	}

	public function adminIpRules() :string {
		return '/admin/ips/rules';
	}

	public function wizard( string $step ) :string {
		return '/admin/wizard/'.$step;
	}

	public function investigateUserSessions() :string {
		return '/admin/activity/sessions';
	}

	public function licenseCheck() :string {
		return '/admin/license';
	}

	public function investigateByIp( string $ip = '' ) :string {
		return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
	}

	public function investigateByPlugin( string $slug = '' ) :string {
		return empty( $slug ) ? '/admin/activity/by_plugin' : '/admin/activity/by_plugin?plugin_slug='.$slug;
	}

	public function investigateByTheme( string $slug = '' ) :string {
		return empty( $slug ) ? '/admin/activity/by_theme' : '/admin/activity/by_theme?theme_slug='.$slug;
	}

	public function investigateByUser( string $lookup = '' ) :string {
		return empty( $lookup ) ? '/admin/activity/by_user' : '/admin/activity/by_user?user_lookup='.$lookup;
	}

	public function investigateByCore() :string {
		return '/admin/activity/by_core';
	}

	public function ipAnalysis( string $ip ) :string {
		return '/admin/ips/rules?analyse_ip='.$ip;
	}

	public function actionsQueueScans( string $zone = 'scans' ) :string {
		$url = '/admin/scans/overview';
		return empty( $zone ) ? $url : $url.'?zone='.$zone;
	}

	public function noncedPluginAction( string $action, ?string $url = null, array $aux = [] ) :string {
		$params = \array_merge( [
			'action'  => 'shield_action',
			'ex'      => $action::SLUG,
			'exnonce' => 'nonce-'.$action::SLUG,
		], $aux );

		$baseUrl = $url ?? '/admin/home';

		return $baseUrl.( \str_contains( $baseUrl, '?' ) ? '&' : '?' ).\implode(
			'&',
			\array_map(
				static fn( string $key, string $value ) :string => $key.'='.$value,
				\array_keys( $params ),
				\array_map( 'strval', \array_values( $params ) )
			)
		);
	}

	public function zone( string $zoneSlug ) :string {
		return $this->configureHome( $zoneSlug );
	}
}

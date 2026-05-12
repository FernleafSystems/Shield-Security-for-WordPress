<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\Handler;

class PageAdminPlugin extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'page_admin_plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/index.twig';
	private const INVESTIGATE_INPUT_KEYS = [ 'user_lookup', 'analyse_ip', 'plugin_slug', 'theme_slug', 'subject' ];

	protected function getRenderData() :array {
		$con = self::con();

		if ( self::con()->isPluginAdmin() ) {
			$nav = sanitize_key( (string)( $this->action_data[ Constants::NAV_ID ] ?? '' ) );
			if ( !PluginNavs::NavExists( $nav ) ) {
				$nav = PluginNavs::NAV_DASHBOARD;
			}
		}
		else {
			$nav = PluginNavs::NAV_RESTRICTED;
		}

		if ( $nav === PluginNavs::NAV_RESTRICTED ) {
			$subNav = PluginNavs::SUBNAV_INDEX;
		}
		else {
			$subNav = sanitize_key( (string)( $this->action_data[ Constants::NAV_SUB_ID ] ?? '' ) );
			if ( !PluginNavs::NavExists( $nav, $subNav ) ) {
				$subNav = PluginNavs::GetDefaultSubNavForNav( $nav );
			}
		}

		$delegateAction = PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ][ $subNav ][ 'handler' ] ?? '';
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable nav handling: '.$nav.' '.$subNav );
		}

		return [
			'classes' => [
				'page_container' => 'page-'.$nav
			],
			'content' => [
				'rendered_page_body' => self::con()->action_router->render(
					$delegateAction::SLUG,
					$this->buildDelegateActionData( $nav, $subNav )
				),
			],
			'flags'   => [
				'is_advanced' => false,
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'logo_small'  => $con->labels->url_img_logo_small,
			],
			'strings' => \array_merge(
				$this->buildAdminShellStrings( $nav, $subNav ),
				[
					'top_page_warnings' => $this->buildTopPageWarnings(),
					'dismiss_notice'    => __( 'Dismiss', 'wp-simple-firewall' ),
				]
			),
			'vars'    => [
				'active_module_settings' => $subNav,
				'nav_sidebar'            => ( new NavMenuBuilder() )->build(),
			],
		];
	}

	private function buildAdminShellStrings( string $nav, string $subNav ) :array {
		$pluginName = self::con()->labels->Name;

		return [
			'admin_shell_title'            => sprintf(
				__( '%1$s - %2$s', 'wp-simple-firewall' ),
				$this->resolveRouteLabel( $nav, $subNav ),
				$pluginName
			),
			'admin_shell_main_label'       => sprintf( __( '%s admin content', 'wp-simple-firewall' ), $pluginName ),
			'admin_shell_sidebar_label'    => sprintf( __( '%s admin sidebar', 'wp-simple-firewall' ), $pluginName ),
			'admin_shell_navigation_label' => sprintf( __( '%s admin navigation', 'wp-simple-firewall' ), $pluginName ),
		];
	}

	private function resolveRouteLabel( string $nav, string $subNav ) :string {
		$navHierarchy = PluginNavs::GetNavHierarchy();
		$navLabel = \trim( (string)( $navHierarchy[ $nav ][ 'name' ] ?? '' ) );
		$routeLabel = \trim( (string)( $navHierarchy[ $nav ][ 'sub_navs' ][ $subNav ][ 'label' ] ?? '' ) );

		return empty( $routeLabel ) ? $navLabel : $routeLabel;
	}

	private function buildDelegateActionData( string $nav, string $subNav ) :array {
		$data = [
			Constants::NAV_ID     => $nav,
			Constants::NAV_SUB_ID => $subNav,
		];

		if ( $nav === PluginNavs::NAV_ACTIVITY ) {
			foreach ( self::INVESTIGATE_INPUT_KEYS as $key ) {
				if ( \array_key_exists( $key, $this->action_data ) ) {
					$data[ $key ] = $this->action_data[ $key ];
				}
			}

			$legacySubjectKey = PluginNavs::investigateSubjectKeyForSubNav( $subNav );
			if ( !empty( $legacySubjectKey ) ) {
				$data[ 'subject' ] = $legacySubjectKey;
			}
		}

		return $data;
	}

	protected function buildTopPageWarnings() :array {
		return \array_filter(
			( new Handler() )->build(),
			function ( array $issue ) {
				return \in_array( 'shield_admin_top_page', $issue[ 'locations' ] );
			}
		);
	}
}

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

	protected function getRenderData() :array {
		$con = self::con();

		if ( self::con()->isPluginAdmin() ) {
			$nav = sanitize_key( (string)$this->action_data[ Constants::NAV_ID ] ?? '' );
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
			$subNav = sanitize_key( (string)$this->action_data[ Constants::NAV_SUB_ID ] ?? '' );
			if ( !PluginNavs::NavExists( $nav, $subNav ) ) {
				$subNav = PluginNavs::GetDefaultSubNavForNav( $nav );
			}
		}

		// The particular renderer for the main page body area, based on navigation
		$delegateAction = PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ][ $subNav ][ 'handler' ] ?? '';
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable nav handling: '.$nav.' '.$subNav );
		}

		return [
			'classes' => [
				'page_container' => 'page-'.$nav
			],
			'content' => [
				'rendered_page_body' => self::con()->action_router->render( $delegateAction::SLUG, [
					Constants::NAV_ID     => $nav,
					Constants::NAV_SUB_ID => $subNav,
				] ),
			],
			'flags'   => [
				'is_advanced' => false,
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'logo_small'  => $con->labels->url_img_logo_small,
			],
			'strings' => [
				'top_page_warnings' => $this->buildTopPageWarnings(),
			],
			'vars'    => [
				'active_module_settings' => $subNav,
				'navbar_menu'            => ( new NavMenuBuilder() )->build(),
			],
		];
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
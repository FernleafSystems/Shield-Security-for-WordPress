<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageAdminPluginRouteResolver {

	private const INVESTIGATE_INPUT_KEYS = [ 'user_lookup', 'analyse_ip', 'plugin_slug', 'theme_slug', 'subject' ];

	/**
	 * @return array{
	 *   nav:string,
	 *   subnav:string,
	 *   mode:string,
	 *   is_mode_landing:bool,
	 *   delegate_action:class-string,
	 *   delegate_payload:array<string,mixed>
	 * }
	 * @throws ActionException
	 */
	public function resolve( array $actionData, bool $isPluginAdmin ) :array {
		$nav = $this->resolveNav( $actionData, $isPluginAdmin );
		$subNav = $this->resolveSubNav( $actionData, $nav );
		$delegateAction = PluginNavs::GetNavHierarchy()[ $nav ][ 'sub_navs' ][ $subNav ][ 'handler' ] ?? '';
		if ( empty( $delegateAction ) ) {
			throw new ActionException( 'Unavailable nav handling: '.$nav.' '.$subNav );
		}

		return [
			'nav'              => $nav,
			'subnav'           => $subNav,
			'mode'             => PluginNavs::modeForRoute( $nav, $subNav ),
			'is_mode_landing'  => PluginNavs::isModeLandingRoute( $nav, $subNav ),
			'delegate_action'  => $delegateAction,
			'delegate_payload' => $this->buildDelegateActionData( $actionData, $nav, $subNav ),
		];
	}

	private function resolveNav( array $actionData, bool $isPluginAdmin ) :string {
		if ( !$isPluginAdmin ) {
			return PluginNavs::NAV_RESTRICTED;
		}

		$nav = sanitize_key( (string)( $actionData[ Constants::NAV_ID ] ?? '' ) );
		return PluginNavs::NavExists( $nav ) ? $nav : PluginNavs::NAV_DASHBOARD;
	}

	private function resolveSubNav( array $actionData, string $nav ) :string {
		if ( $nav === PluginNavs::NAV_RESTRICTED ) {
			return PluginNavs::SUBNAV_INDEX;
		}

		$subNav = sanitize_key( (string)( $actionData[ Constants::NAV_SUB_ID ] ?? '' ) );
		return PluginNavs::NavExists( $nav, $subNav ) ? $subNav : PluginNavs::GetDefaultSubNavForNav( $nav );
	}

	private function buildDelegateActionData( array $actionData, string $nav, string $subNav ) :array {
		$data = [
			Constants::NAV_ID     => $nav,
			Constants::NAV_SUB_ID => $subNav,
		];

		if ( $nav === PluginNavs::NAV_ACTIVITY ) {
			foreach ( self::INVESTIGATE_INPUT_KEYS as $key ) {
				if ( \array_key_exists( $key, $actionData ) ) {
					$data[ $key ] = $actionData[ $key ];
				}
			}

			$canonicalSubjectKey = PluginNavs::investigateSubjectKeyForSubNav( $subNav );
			if ( !empty( $canonicalSubjectKey ) ) {
				$data[ 'subject' ] = $canonicalSubjectKey;
			}
		}

		return $data;
	}
}

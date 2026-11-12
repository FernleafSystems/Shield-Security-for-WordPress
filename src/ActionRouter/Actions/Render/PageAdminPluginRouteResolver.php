<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;

/**
 * Turns an admin page URL's query values into the route and the data each page renders with. Those values come
 * from the URL, so this is where they're checked: only text is used, and only the keys a page needs are passed on.
 * The pages read the data they're given rather than the request.
 */
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
		$actionData = $this->canonicalActionData( $actionData );
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

	/**
	 * @return array{nav:string,nav_sub:string,network_invite?:string}
	 */
	public function buildAdminPageActionData( array $actionData ) :array {
		$actionData = $this->canonicalActionData( $actionData );
		$nav = $this->resolveNav( $actionData, true );
		$subNav = $this->resolveSubNav( $actionData, $nav );

		return $this->buildDelegateActionData( $actionData, $nav, $subNav );
	}

	private function canonicalActionData( array $data ) :array {
		$route = PluginURLs::legacyAdminRouteParams(
			ActionData::TransportField( $data, Constants::NAV_ID ),
			ActionData::TransportField( $data, Constants::NAV_SUB_ID )
		);
		if ( isset( $route[ 'wp_admin' ] ) ) {
			throw new ActionException( 'This destination is a WordPress admin screen, not a Shield render action.' );
		}
		return \array_replace( $data, $route ?? [] );
	}

	private function resolveNav( array $actionData, bool $isPluginAdmin ) :string {
		if ( !$isPluginAdmin ) {
			return PluginNavs::NAV_RESTRICTED;
		}

		$nav = sanitize_key( ActionData::TransportField( $actionData, Constants::NAV_ID ) );
		return PluginNavs::NavExists( $nav ) ? $nav : PluginNavs::NAV_DASHBOARD;
	}

	private function resolveSubNav( array $actionData, string $nav ) :string {
		if ( $nav === PluginNavs::NAV_RESTRICTED ) {
			return PluginNavs::SUBNAV_INDEX;
		}

		$subNav = sanitize_key( ActionData::TransportField( $actionData, Constants::NAV_SUB_ID ) );
		return PluginNavs::NavExists( $nav, $subNav ) ? $subNav : PluginNavs::GetDefaultSubNavForNav( $nav );
	}

	private function buildDelegateActionData( array $actionData, string $nav, string $subNav ) :array {
		$data = [
			Constants::NAV_ID     => $nav,
			Constants::NAV_SUB_ID => $subNav,
		];

		if ( $nav === PluginNavs::NAV_ACTIVITY ) {
			foreach ( self::INVESTIGATE_INPUT_KEYS as $key ) {
				if ( \is_string( $actionData[ $key ] ?? null ) ) {
					$data[ $key ] = $actionData[ $key ];
				}
			}

			$canonicalSubjectKey = PluginNavs::investigateSubjectKeyForSubNav( $subNav );
			if ( !empty( $canonicalSubjectKey ) ) {
				$data[ 'subject' ] = $canonicalSubjectKey;
			}
		}

		$workspace = $actionData[ 'workspace' ] ?? '';
		if ( $nav === PluginNavs::NAV_REPORTS && \is_string( $workspace )
			 && isset( PluginNavs::reportsWorkspaceDefinitions()[ $workspace ] ) ) {
			$data[ 'workspace' ] = $workspace;
		}
		if ( $nav === PluginNavs::NAV_SCANS ) {
			foreach ( [ 'zone', 'step' ] as $key ) {
				if ( \is_string( $actionData[ $key ] ?? null ) ) {
					$data[ $key ] = sanitize_key( $actionData[ $key ] );
				}
			}
		}
		if ( $nav === PluginNavs::NAV_ZONES ) {
			foreach ( [ 'zone', 'row_key', 'config_item', 'component' ] as $key ) {
				if ( isset( $actionData[ $key ] ) && \is_string( $actionData[ $key ] ) ) {
					$data[ $key ] = sanitize_key( $actionData[ $key ] );
				}
			}
		}
		return $this->withNetworkInviteReviewData( $data, $actionData, $nav, $subNav );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function withNetworkInviteReviewData( array $data, array $actionData, string $nav, string $subNav ) :array {
		if ( $nav === PluginNavs::NAV_TOOLS
			 && $subNav === PluginNavs::SUBNAV_TOOLS_IMPORT
			 && \is_string( $actionData[ NetworkInviteRepository::REVIEW_QUERY_KEY ] ?? null ) ) {
			$data[ NetworkInviteRepository::REVIEW_QUERY_KEY ] = sanitize_key( $actionData[ NetworkInviteRepository::REVIEW_QUERY_KEY ] );
		}

		return $data;
	}
}

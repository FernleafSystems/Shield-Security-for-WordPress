<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

/**
 * @phpstan-type SecAdminContextualHref array{
 *   title:string,
 *   href:string
 * }
 * @phpstan-type SecAdminZoneAction array{
 *   title:string,
 *   href:string,
 *   icon:string,
 *   classes:list<string>
 * }
 * @phpstan-type SecAdminGoalLink array{
 *   label:string,
 *   href:string,
 *   icon_class:string
 * }
 */
class SecurityAdminDisableActionBuilder {

	use PluginControllerConsumer;

	/**
	 * @return ?SecAdminContextualHref
	 */
	public function buildContextualHref() :?array {
		if ( !$this->canBuild() ) {
			return null;
		}

		return [
			'title' => $this->label(),
			'href'  => $this->buildHref( self::con()->plugin_urls->adminHome() ),
		];
	}

	/**
	 * A quiet link on the Security Admin goal's title line, while Security Admin is on.
	 * @return list<SecAdminGoalLink>
	 */
	public function buildConfigureGoalLinks() :array {
		if ( !$this->canBuild() ) {
			return [];
		}

		return [
			[
				'label'      => $this->label(),
				'icon_class' => $this->iconClass(),
				'href'       => $this->buildHref( self::con()->plugin_urls->configureHome( Secadmin::Slug() ) ),
			],
		];
	}

	/**
	 * @return ?SecAdminZoneAction
	 */
	public function buildZoneAction() :?array {
		if ( !$this->canBuild() ) {
			return null;
		}

		return [
			'title'   => $this->label(),
			'href'    => $this->buildHref( self::con()->plugin_urls->adminHome() ),
			'icon'    => $this->iconClass(),
			'classes' => [
				'list-group-item-warning',
			],
		];
	}

	private function buildHref( string $baseUrl ) :string {
		return self::con()->plugin_urls->noncedPluginAction(
			SecurityAdminRemove::class,
			$baseUrl
		);
	}

	private function canBuild() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin();
	}

	private function iconClass() :string {
		return self::con()->svgs->iconClass( 'toggle-off' );
	}

	private function label() :string {
		return __( 'Disable Security Admin', 'wp-simple-firewall' );
	}
}

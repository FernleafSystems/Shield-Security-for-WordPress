<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component,
	Components
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Modules\ModulePlugin;

abstract class MeterBase {

	use PluginControllerConsumer;

	public const SLUG = '';

	public function warning() :array {
		$warning = [];
		if ( !self::con()->comps->opts_lookup->isPluginEnabled() ) {
			$warning = [
				'text' => __( 'The plugin is currently entirely disabled.' ),
				'href' => self::con()->plugin_urls->cfgForZoneComponent( ModulePlugin::Slug() ),
			];
		}
		return $warning;
	}

	public function buildComponents() :array {
		$con = self::con();
		$prefs = $con->opts->optGet( 'sec_overview_prefs' );

		$viewAs = $prefs[ 'view_as' ] ?? '';
		if ( !\in_array( $viewAs, [ 'free', 'starter', 'business' ], true ) ) {
			$viewAs = $con->isPremiumActive() ? 'business' : 'free';
		}
		elseif ( $viewAs === 'free' && $con->isPremiumActive() ) {
			$viewAs = 'business';
		}

		$prefs[ 'view_as' ] = $viewAs;
		$con->opts->optSet( 'sec_overview_prefs', $prefs );

		$componentClasses = \array_filter(
			\array_intersect( $this->getComponents(), Components::COMPONENTS ),
			function ( $componentClass ) use ( $viewAs ) {
				switch ( $viewAs ) {
					case 'business':
						$show = true;
						break;
					case 'starter':
						$show = \in_array( $componentClass::MINIMUM_EDITION, [ 'free', 'starter' ] );
						break;
					case 'free':
					default:
						$show = $componentClass::MINIMUM_EDITION === 'free';
						break;
				}
				return $show;
			}
		);

		$components = [];
		$builder = new Components();
		foreach ( $componentClasses as $class ) {
			try {
				$built = $builder->buildComponent( $class );
				$components[ $built[ 'slug' ] ] = $built;
			}
			catch ( \Exception $e ) {
			}
		}
		return $components;
	}

	/**
	 * @return Component\Base[]|string[]
	 */
	protected function getComponents() :array {
		return [];
	}

	public function title() :string {
		return 'no title';
	}

	public function subtitle() :string {
		return 'no subtitle';
	}

	public function description() :array {
		return [ 'no description' ];
	}
}
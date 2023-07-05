<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	BaseShield,
	Plugin,
	Plugin\Lib\MeterAnalysis\Components,
	PluginControllerConsumer
};

abstract class MeterBase {

	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @return BaseShield\ModCon[]|Plugin\ModCon[]
	 */
	protected function getWorkingMods() :array {
		return [];
	}

	public function warning() :array {
		$con = $this->con();
		$pluginMod = $con->getModule_Plugin();
		/** @var Plugin\Options $pluginOpts */
		$pluginOpts = $pluginMod->getOptions();
		$warning = [];
		if ( $pluginOpts->isPluginGloballyDisabled() ) {
			$warning = [
				'text' => __( 'The plugin is currently entirely disabled.' ),
				'href' => $con->plugin_urls->modCfgOption( 'global_enable_plugin_features' ),
			];
		}
		else {
			foreach ( $this->getWorkingMods() as $workingMod ) {
				if ( !$workingMod->isModOptEnabled() ) {
					$warning = [
						'text' => __( 'A module that manages some of these settings is disabled.' ),
						'href' => $con->plugin_urls->modCfgOption( $workingMod->getEnableModOptKey() ),
					];
					break;
				}
			}
		}
		return $warning;
	}

	public function buildComponents() :array {
		$con = $this->con();
		$pluginOpts = $con->getModule_Plugin()->getOptions();
		$prefs = $pluginOpts->getOpt( 'sec_overview_prefs' );

		$viewAs = $prefs[ 'view_as' ] ?? '';
		if ( $viewAs === 'pro' ) {
			$viewAs = 'business';
		}
		elseif ( !\in_array( $viewAs, [ 'free', 'starter', 'business' ], true ) ) {
			$viewAs = $con->isPremiumActive() ? 'business' : 'free';
		}

		$prefs[ 'view_as' ] = $viewAs;
		$pluginOpts->setOpt( 'sec_overview_prefs', $prefs );

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
	 * @return Plugin\Lib\MeterAnalysis\Component\Base[]|string[]
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
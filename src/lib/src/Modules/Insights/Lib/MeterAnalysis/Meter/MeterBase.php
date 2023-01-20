<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	BaseShield,
	Insights\Lib\MeterAnalysis\Components,
	Plugin,
	PluginControllerConsumer};

abstract class MeterBase {

	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @return BaseShield\ModCon[]|Plugin\ModCon[]
	 */
	protected function getWorkingMods() :array {
		return [];
	}

	public function buildMeter() :array {
		return $this->postProcessMeter( [
			'title'       => $this->title(),
			'subtitle'    => $this->subtitle(),
			'warning'     => $this->warning(),
			'description' => $this->description(),
			'components'  => $this->buildComponents(),
		] );
	}

	public function warning() :array {
		$con = $this->getCon();
		$pluginMod = $con->getModule_Plugin();
		/** @var Plugin\Options $pluginOpts */
		$pluginOpts = $pluginMod->getOptions();
		$warning = [];
		if ( $pluginOpts->isPluginGloballyDisabled() ) {
			$warning = [
				'text' => __( 'The plugin is currently entirely disabled.' ),
				'href' => $con->plugin_urls->modOption( $pluginMod, 'global_enable_plugin_features' ),
			];
		}
		else {
			foreach ( $this->getWorkingMods() as $workingMod ) {
				if ( !$workingMod->isModOptEnabled() ) {
					$warning = [
						'text' => __( 'A module that manages some of these settings is disabled.' ),
						'href' => $con->plugin_urls->modOption( $workingMod, $workingMod->getEnableModOptKey() ),
					];
					break;
				}
			}
		}
		return $warning;
	}

	public function buildComponents() :array {
		$builder = ( new Components() )->setCon( $this->getCon() );
		$components = [];
		foreach ( array_intersect( $this->getComponents(), $builder::COMPONENTS ) as $class ) {
			try {
				$built = $builder->buildComponent( $class );
				$components[ $built[ 'slug' ] ] = $built;
			}
			catch ( \Exception $e ) {
			}
		}
		return $components;
	}

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
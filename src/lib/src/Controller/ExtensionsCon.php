<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Extensions\BaseExtension;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ExtensionsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @return BaseExtension[]
	 */
	private $extensions = null;

	protected function canRun() :bool {
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' ) && self::con()->isPremiumActive();
	}

	protected function run() {
		$this->initExtensions();
		add_filter( 'shield/collate_rule_builders', [ $this, 'extendRuleBuilders' ] );
	}

	private function initExtensions() {
		foreach ( $this->getAvailableExtensions() as $ext ) {
			$extCfg = $ext->cfg();

			$theMod = self::con()->getModule( $extCfg->mod_slug );
			if ( $theMod ) {

				$modCfg = $theMod->cfg;
				$modCfgOpts = $modCfg->options;
				$modCfgSections = $modCfg->sections;

				foreach ( $extCfg->options as $opt ) {
					$modCfgOpts[ $opt[ 'key' ] ] = $opt;
				}

				foreach ( $extCfg->sections as $newSection ) {
					foreach ( $modCfgSections as $existingSection ) {
						if ( $existingSection[ 'slug' ] === $newSection[ 'slug' ] ) {
							break 2;
						}
					}
					$modCfgSections[] = $newSection;
				}

				$modCfg->options = $modCfgOpts;
				$modCfg->sections = $modCfgSections;

				$ext->execute();
			}
		}
	}

	public function extendRuleBuilders( array $rules ) :array {
		foreach ( $this->getExtensions() as $extension ) {
			$rules = \array_merge( $rules, $extension->getRuleBuilders() );
		}
		return $rules;
	}

	/**
	 * @return BaseExtension[]
	 */
	protected function getExtensions() :array {
		if ( $this->extensions === null ) {
			$extensions = apply_filters( 'shield/get_extensions', [] );
			$this->extensions = \array_filter(
				\is_array( $extensions ) ? $extensions : [],
				function ( $ext ) {
					return \is_a( $ext, BaseExtension::class );
				}
			);
		}
		return $this->extensions;
	}

	/**
	 * @return BaseExtension[]
	 */
	protected function getAvailableExtensions() :array {
		return \array_filter( $this->getExtensions(), function ( $ext ) {
			return $ext->isAvailable();
		} );
	}
}

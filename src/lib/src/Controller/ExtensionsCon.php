<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Extensions\BaseExtension;
use FernleafSystems\Wordpress\Plugin\Shield\Extensions\ProxyCheck\ExtProxyCheck;
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
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' ) && self::con()->caps->canRunShieldAddons();
	}

	protected function run() {
		$this->initExtensions();
		add_filter( 'shield/collate_rule_builders', [ $this, 'extendRuleBuilders' ] );
	}

	private function initExtensions() :void {
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

	public function getExtension( string $slug ) :?BaseExtension {
		return $this->getAvailableExtensions()[ $slug ] ?? null;
	}

	public function getExtension_ProxyCheck() :ExtProxyCheck {
		return $this->getExtension( ExtProxyCheck::SLUG );
	}

	/**
	 * @return BaseExtension[]
	 */
	protected function getExtensions() :array {
		if ( $this->extensions === null ) {
			$this->extensions = [];
			/** @var BaseExtension $ext */
			foreach ( apply_filters( 'shield/get_extensions', [] ) as $ext ) {
				if ( \is_object( $ext ) && \is_a( $ext, BaseExtension::class ) ) {
					$this->extensions[ $ext->cfg()->slug ] = $ext;
				}
			}
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

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\NormaliseConfigComponents;
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
		return Services::Data()->getPhpVersionIsAtLeast( '7.4' );
	}

	protected function run() {
		$this->initExtensions();
		$this->extendRules();
	}

	private function initExtensions() :void {
		foreach ( $this->getAvailableExtensions() as $ext ) {

			add_action( 'shield/modules_configuration', function () use ( $ext ) {
				$normaliser = new NormaliseConfigComponents();
				$configuration = self::con()->cfg->configuration;
				$configuration->sections = \array_merge( $configuration->sections, $normaliser->indexSections( $ext->cfg()->sections ) );
				$configuration->options = \array_merge( $configuration->options, $normaliser->indexOptions( $ext->cfg()->options ) );
				self::con()->cfg->configuration = $configuration;
			}, 10, 0 );

			$ext->execute();
		}
	}

	public function extendRules() :void {
		add_filter( 'shield/rules/enum_conditions', function ( array $conditions ) {
			foreach ( $this->getExtensions() as $extension ) {
				if ( $extension->canExtendRules() ) {
					$conditions = \array_merge( $conditions, $extension->getRuleConditions() );
				}
			}
			return \array_unique( $conditions );
		} );
		add_filter( 'shield/rules/enum_responses', function ( array $responses ) {
			foreach ( $this->getExtensions() as $extension ) {
				if ( $extension->canExtendRules() ) {
					$responses = \array_merge( $responses, $extension->getRuleResponses() );
				}
			}
			return \array_unique( $responses );
		} );
		add_filter( 'shield/collate_rule_builders', function ( array $builders ) {
			foreach ( $this->getExtensions() as $extension ) {
				if ( $extension->canExtendRules() ) {
					$builders = \array_merge( $builders, $extension->getRuleBuilders() );
				}
			}
			return \array_unique( $builders );
		} );
		add_filter( 'shield/rules/enum_types', function ( array $builders ) {
			foreach ( $this->getExtensions() as $extension ) {
				if ( $extension->canExtendRules() ) {
					$builders = \array_merge( $builders, $extension->getRuleEnumTypes() );
				}
			}
			return \array_unique( $builders );
		} );
	}

	/**
	 * @throws \Exception
	 */
	public function getExtension( string $slug ) :BaseExtension {
		$ext = $this->getAvailableExtensions()[ $slug ] ?? null;
		if ( empty( $ext ) ) {
			throw new \Exception( sprintf( '%s extension is unavailable.', $slug ) );
		}
		return $ext;
	}

	/**
	 * @return BaseExtension[]
	 */
	protected function getExtensions() :array {
		if ( $this->extensions === null ) {
			$this->extensions = [];
			/** @var BaseExtension $ext */
			foreach ( apply_filters( 'shield/get_extensions', [], $this ) as $ext ) {
				if ( \is_object( $ext ) && \is_a( $ext, BaseExtension::class ) ) {
					$this->extensions[ $ext::SLUG ] = $ext;
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
			return $ext->isAvailable() && \in_array( $ext::SLUG, EnumExtensions::All() );
		} );
	}
}
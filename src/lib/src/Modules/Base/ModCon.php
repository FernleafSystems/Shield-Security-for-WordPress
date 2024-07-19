<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property bool $is_booted
 * @deprecated 19.2
 */
class ModCon extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @return null|Options|mixed
	 * @deprecated 19.2
	 */
	public function opts() {
		$element = false;
		try {
			$C = $this->findElementClass( 'Options' );
			$element = @\class_exists( $C ) ? new $C() : false;
			if ( \method_exists( $element, 'setMod' ) ) {
				$element->setMod( $this );
			}
		}
		catch ( \Exception $e ) {
		}
		return $element;
	}

	/**
	 * @throws \Exception
	 * @deprecated 19.2
	 */
	public function findElementClass( string $element ) :string {
		$theClass = null;

		$roots = \array_map(
			function ( $root ) {
				return \rtrim( $root, '\\' ).'\\';
			},
			[
				( new \ReflectionClass( $this ) )->getNamespaceName(),
				__NAMESPACE__
			]
		);

		foreach ( $roots as $NS ) {
			$maybe = $NS.$element;
			if ( @\class_exists( $maybe ) ) {
				if ( ( new \ReflectionClass( $maybe ) )->isInstantiable() ) {
					$theClass = $maybe;
					break;
				}
			}
		}

		if ( $theClass === null ) {
			throw new \Exception( sprintf( 'Could not find class for element "%s".', $element ) );
		}
		return $theClass;
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
	}

	/**
	 * @deprecated 19.2
	 */
	public function isModuleEnabled() :bool {
		return true;
	}

	/**
	 * @deprecated 19.2
	 */
	public function isModOptEnabled() :bool {
		return true;
	}

	/**
	 * @deprecated 19.2
	 */
	public function getEnableModOptKey() :string {
		return 'enable_'.$this->cfg->slug;
	}

	/**
	 * @deprecated 19.2
	 */
	public function getTextOptDefault( string $key ) :string {
		return 'Undefined Text Opt Default';
	}
}
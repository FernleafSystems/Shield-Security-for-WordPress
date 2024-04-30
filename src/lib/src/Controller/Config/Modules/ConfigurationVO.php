<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array $modules
 * @property array $sections
 * @property array $options
 * @property array $defs
 * @property array $admin_notices
 * @property array $databases
 * @property array $events
 */
class ConfigurationVO extends DynPropertiesClass {

	public function def( string $key ) {
		return $this->defs[ $key ] ?? null;
	}

	public function modFromOpt( string $key ) :?string {
		$optDef = $this->options[ $key ] ?? null;
		return empty( $optDef ) ? null : $this->sections[ $optDef[ 'section' ] ][ 'module' ];
	}

	public function optsForSection( string $section ) :array {
		return \array_filter(
			$this->options,
			function ( array $opt ) use ( $section ) {
				return $opt[ 'section' ] === $section;
			}
		);
	}

	public function optsForModule( string $module ) :array {
		$sections = \array_keys( $this->sectionsForModule( $module ) );
		return \array_filter(
			$this->options,
			function ( array $opt ) use ( $sections ) {
				return \in_array( $opt[ 'section' ], $sections );
			}
		);
	}

	public function sectionsForModule( string $module ) :array {
		return \array_filter(
			$this->sections,
			function ( array $sec ) use ( $module ) {
				return !empty( $sec[ 'module' ] ) && $sec[ 'module' ] === $module;
			}
		);
	}

	public function transferableOptions() :array {
		return \array_filter(
			$this->options,
			function ( array $option ) {
				return $option[ 'transferable' ] ?? true;
			}
		);
	}

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'admin_notices':
			case 'modules':
			case 'sections':
			case 'options':
			case 'defs':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}
}
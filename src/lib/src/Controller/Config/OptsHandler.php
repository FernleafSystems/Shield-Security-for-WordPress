<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Base,
	License,
	PluginControllerConsumer
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property array[] $mod_opts_free
 * @property array[] $mod_opts_pro
 * @property array[] $mod_opts_all
 */
class OptsHandler extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const TYPE_ALL = 'all';
	public const TYPE_FREE = 'free';
	public const TYPE_PRO = 'pro';

	private $changes = [];

	private $values = null;

	private $merged = false;

	public function __get( string $key ) {
		$val = parent::__get( $key );

		$WP = Services::WpGeneral();

		if ( $val === null ) {

			if ( \preg_match( sprintf( '#^mod_opts_(%s|%s|%s)$#', self::TYPE_FREE, self::TYPE_PRO, self::TYPE_ALL ), $key, $matches ) ) {

				$type = $matches[ 1 ];
				if ( $type === self::TYPE_ALL ) {
					if ( self::con()->plugin_reset ) {
						$val = $this->defaultAllStorageStruct();
					}
					else {
						$val = $WP->getOption( $this->key( self::TYPE_ALL ) );
						if ( !\is_array( $val ) ) {
							$val = [];
						}
					}
				}
				elseif ( self::con()->plugin_reset ) {
					$val = [];
				}
				else {
					$val = $WP->getOption( $this->key( $type ) );
					if ( !\is_array( $val ) ) {
						$val = [];
					}
				}

				$this->{$key} = $val;
			}
		}

		return $val;
	}

	public function values() :array {

		if ( $this->values === null ) {

			$all = $this->mod_opts_all;
			if ( empty( $all ) || empty( $all[ 'values' ][ self::TYPE_FREE ] ) || empty( $all[ 'values' ][ self::TYPE_PRO ] ) ) {
				$all = $this->flatten();
			}

			$this->mod_opts_all = $all;
			$this->values = $all[ 'values' ][ self::TYPE_FREE ] ?? [];
		}

		if ( !$this->merged ) {
			$this->merged = true;
			if ( self::con()->isPremiumActive() ) {
				$this->values = \array_merge( $this->values, $this->mod_opts_all[ 'values' ][ self::TYPE_PRO ] );
			}
			$this->values = \array_intersect_key( $this->values, self::con()->cfg->configuration->options );
		}

		return $this->values;
	}

	public function resetToDefaults() {
		$this->values = $this->mod_opts_free = $this->mod_opts_pro = $this->mod_opts_all = [];
		$this->delete();
	}

	private function key( string $type ) :string {
		return self::con()->prefix( sprintf( 'opts_%s', $type ), '_' );
	}

	public function delete() :void {
		foreach ( [ self::TYPE_ALL, self::TYPE_PRO, self::TYPE_FREE ] as $type ) {
			Services::WpGeneral()->deleteOption( $this->key( $type ) );
		}
	}

	public function store() {
		$con = self::con();
		if ( !$con->plugin_deleting ) {
			add_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
			$this->preStore();

			if ( \method_exists( $this, 'values' ) ) {
				Services::WpGeneral()->updateOption( $this->key( self::TYPE_ALL ), $this->mod_opts_all );
			}
			else {
				foreach ( [ self::TYPE_PRO, self::TYPE_FREE ] as $type ) {
					Services::WpGeneral()->updateOption( $this->key( $type ), $this->{'mod_opts_'.$type} );
				}
			}

			$this->postStore();
			remove_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		}
	}

	/**
	 * Used only once during migration from other stored data. Can be completely removed eventually.
	 */
	private function flatten() :array {
		error_log( __FUNCTION__ );
		$toStore = [
			'version' => self::con()->cfg->version(),
			'values'  => [
				self::TYPE_FREE => [],
				self::TYPE_PRO  => [],
			],
		];
		$extras = [
			'xfer_excluded' => [],
		];

		$flatFree = [];
		foreach ( $this->mod_opts_free as $modOpts ) {
			foreach ( $extras as $key => $data ) {
				if ( !empty( $modOpts[ $key ] ?? null ) && \is_array( $modOpts[ $key ] ) ) {
					$extras[ $key ] = \array_unique( \array_merge( $data, $modOpts[ $key ] ) );
				}
				unset( $modOpts[ $key ] );
			}
			$flatFree = \array_merge( $flatFree, $modOpts );
		}
		\ksort( $flatFree );
		$flatFree = \array_map(
			function ( $optValue ) {
				if ( \is_array( $optValue ) ) {
					\ksort( $optValue );
				}
				return $optValue;
			},
			$flatFree
		);

		$flatPro = [];
		foreach ( $this->mod_opts_pro as $modOpts ) {
			foreach ( $extras as $key => $data ) {
				if ( !empty( $modOpts[ $key ] ?? null ) && \is_array( $modOpts[ $key ] ) ) {
					$extras[ $key ] = \array_unique( \array_merge( $data, $modOpts[ $key ] ) );
				}
				unset( $modOpts[ $key ] );
			}
			foreach ( $modOpts as $optKey => $optValue ) {
				$store = false;
				if ( !isset( $flatFree[ $optKey ] ) ) {
					$flatFree[ $optKey ] = $optValue;
				}
				elseif ( \is_scalar( $optValue ) ) {
					if ( $optValue !== $flatFree[ $optKey ] ) {
						$store = true;
					}
				}
				elseif ( \is_array( $optValue ) ) {
					\ksort( $optValue );
					if ( \serialize( $optValue ) !== \serialize( $flatFree[ $optKey ] ) ) {
						$store = true;
					}
				}
				else {
					$store = true;
				}

				if ( $store ) {
					$flatPro[ $optKey ] = $optValue;
				}
			}
		}
		\ksort( $flatPro );

		$toStore[ 'values' ][ self::TYPE_FREE ] = $flatFree;
		$toStore[ 'values' ][ self::TYPE_PRO ] = $flatPro;

		return \array_merge( $toStore, $extras );
	}

	private function preStore() {
		$con = self::con();

		// Pre-process options.
		foreach ( $con->modules as $mod ) {
			$mod->opts()->preSave();
		}

		do_action( $con->prefix( 'pre_options_store' ) );

		if ( !empty( $this->changes ) ) {
			( new Opts\FireEventsForChangedOpts() )->run( $this->changes );
		}

		$this->transposeValuesToStore();

		do_action( $con->prefix( 'after_pre_options_store' ), !empty( $this->changes ) );
	}

	private function transposeValuesToStore() :void {
		$con = self::con();

		$freeValues = $this->mod_opts_all[ 'values' ][ self::TYPE_FREE ];
		foreach ( $this->values() as $optKey => $optValue ) {
			// if it's a premium option, set it to default on the free set.
			$freeValues[ $optKey ] = ( $this->optDef( $optKey )[ 'premium' ] ?? false ) ?
				$this->optDefault( $optKey ) : $optValue;
			if ( \is_array( $freeValues[ $optKey ] ) ) {
				\ksort( $freeValues[ $optKey ] );
			}
		}
		\ksort( $freeValues );

		$proValues = [];
		foreach ( $con->isPremiumActive() ? $this->values() : $this->mod_opts_all[ 'values' ][ self::TYPE_PRO ] as $optKey => $optValue ) {
			$store = false;
			if ( !isset( $freeValues[ $optKey ] ) ) {
				$freeValues[ $optKey ] = $optValue;
			}
			elseif ( \is_scalar( $optValue ) ) {
				if ( $optValue !== $freeValues[ $optKey ] ) {
					$store = true;
				}
			}
			elseif ( \is_array( $optValue ) ) {
				\ksort( $optValue );
				if ( \serialize( $optValue ) !== \serialize( $freeValues[ $optKey ] ) ) {
					$store = true;
				}
			}
			else {
				$store = true; // ?
			}

			if ( $store ) {
				$proValues[ $optKey ] = $optValue;
			}
		}
		\ksort( $proValues );

		$all[ 'values' ][ self::TYPE_FREE ] = $freeValues;
		$all[ 'values' ][ self::TYPE_PRO ] = $proValues;

		$this->mod_opts_all = $all;
	}

	private function postStore() {
		if ( \method_exists( $this, 'optGet' ) ) {
			$this->changes = [];
		}
		else {
			foreach ( self::con()->modules as $mod ) {
				$mod->opts()->resetChangedOpts();
			}
		}
	}

	public function hasChanges() :bool {
		return !empty( $this->changes );
	}

	public function optCap( string $key ) :?string {
		return $this->optDef( $key )[ 'cap' ] ?? null;
	}

	public function optChanged( string $key ) :bool {
		return isset( $this->changes[ $key ] );
	}

	/**
	 * Use only when you're sure the option key exists.
	 */
	public function optDef( string $key ) :array {
		return self::con()->cfg->configuration->options[ $key ];
	}

	public function optDefault( string $key ) {
		return $this->optDef( $key )[ 'default' ];
	}

	public function optEnforceValueType( string $key, $value ) {
		switch ( $this->optType( $key ) ) {
			case 'boolean':
				$value = (bool)$value;
				break;
			case 'integer':
				$value = (int)$value;
				break;
			case 'text':
				$value = (string)$value;
				break;
			case 'array':
			case 'multiple_select':
				if ( !\is_array( $value ) ) {
					$value = (array)$value;
				}
				break;
			default:
				break;
		}
		return $value;
	}

	public function optExists( string $key ) :bool {
		return isset( self::con()->cfg->configuration->options[ $key ] );
	}

	public function optGet( string $key ) {
		$value = $this->values()[ $key ] ?? null;

		if ( $this->optExists( $key ) ) {

			if ( $value === null || !$this->optIsValueTypeValid( $key, $value ) ) {
				$this->optReset( $key );
			}

			$cap = $this->optCap( $key );
			if ( !empty( $cap ) && !self::con()->caps->hasCap( $cap ) ) {
				$value = $this->optDefault( $key );
			}
			$value = $this->optEnforceValueType( $key, $value );
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 */
	public function optIs( string $key, $value ) :bool {
		return $this->optGet( $key ) == $value;
	}

	public function optReset( string $key ) :void {
		$this->optSet( $key, $this->optDefault( $key ) );
	}

	public function optSet( string $key, $newValue ) :self {
		try {
			/** Don't use optGet() */
			$current = $this->values[ $key ] ?? null;
			$newValue = ( new Opts\PreSetOptSanitize( $key, $newValue ) )->run();

			// Here we try to ensure that values that are repeatedly changed properly reflect their changed
			// states, as they may be reverted to their original state and we "think" it's been changed.
			$valueIsDifferent = \serialize( $current ) !== \serialize( $newValue );
			// basically if we're actually resetting back to the original value
			$isResetToOriginal = $valueIsDifferent
								 && isset( $this->changes[ $key ] )
								 && ( \serialize( $this->changes[ $key ] ) === \serialize( $newValue ) );

			if ( $valueIsDifferent ) {
				if ( !isset( $this->changes[ $key ] ) ) {
					$this->changes[ $key ] = $current;
				}
				$this->values[ $key ] = $newValue;
			}

			if ( $isResetToOriginal ) {
				unset( $this->changes[ $key ] );
			}
		}
		catch ( \Exception $e ) {
			// new value was invalid and will not be stored.
		}
		return $this;
	}

	public function optType( string $key ) :string {
		return $this->optDef( $key )[ 'type' ];
	}

	private function optIsValueTypeValid( string $key, $value ) :bool {
		switch ( $this->optType( $key ) ) {
			case 'array':
			case 'multiple_select':
				$valid = \is_array( $value );
				break;
			case 'integer':
				$valid = \is_numeric( $value );
				break;
			default:
				$valid = true;
				break;
		}
		return $valid;
	}

	public function getXferExcluded() :array {
		return $this->mod_opts_all[ 'xfer_excluded' ] ?? [];
	}

	public function setXferExcluded( array $excluded ) :void {
		$all = $this->mod_opts_all;
		$all[ 'xfer_excluded' ] = $excluded;
		$this->mod_opts_all = $all;
	}

	/**
	 * @deprecated 19.0.7
	 */
	public function commit() :void {
		$this->store();
	}

	private function defaultAllStorageStruct() :array {
		return [
			'version'       => self::con()->cfg->version(),
			'values'        => [
				self::TYPE_FREE => [],
				self::TYPE_PRO  => [],
			],
			'xfer_excluded' => [],
		];
	}

	/**
	 * @param Base\ModCon|mixed $mod
	 * @deprecated 19.1
	 */
	public function getFor( $mod ) :?array {
		$opts = $this->mod_opts_free[ $mod->cfg->slug ] ?? null;
		if ( $mod->cfg->slug !== License\ModCon::SLUG && self::con()->isPremiumActive() ) {
			$premiumOpts = $this->mod_opts_pro[ $mod->cfg->slug ] ?? null;
			if ( \is_array( $premiumOpts ) ) {
				$opts = $premiumOpts;
			}
		}
		return $opts;
	}

	/**
	 * @param Base\ModCon|mixed $mod
	 * @deprecated 19.1
	 */
	public function setFor( $mod, array $values, ?string $type = null ) :self {

		if ( $mod->cfg->slug === License\ModCon::SLUG ) {
			$type = self::TYPE_FREE;
		}
		elseif ( !\in_array( $type, [ self::TYPE_PRO, self::TYPE_FREE ], true ) ) {
			$type = self::con()->isPremiumActive() ? self::TYPE_PRO : self::TYPE_FREE;
		}

		$opts = $mod->opts();

		if ( $type === self::TYPE_FREE ) {
			foreach ( $mod->cfg->options as $opt ) {
				if ( ( $opt[ 'premium' ] ?? false )
					 && isset( $values[ $opt[ 'key' ] ] )
					 && $values[ $opt[ 'key' ] ] !== $opts->getOptDefault( $opt[ 'key' ] )
				) {
					$values[ $opt[ 'key' ] ] = $opts->getOptDefault( $opt[ 'key' ] );
				}
			}
		}

		$allOptsValues = $this->{'mod_opts_'.$type};
		$allOptsValues[ $mod->cfg->slug ] = $values;
		$this->{'mod_opts_'.$type} = \array_intersect_key( $allOptsValues, self::con()->modules );

		if ( $type === self::TYPE_PRO ) {
			$this->setFor( $mod, $values, self::TYPE_FREE );
		}

		return $this;
	}
}
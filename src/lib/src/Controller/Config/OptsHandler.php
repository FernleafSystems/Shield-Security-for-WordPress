<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Base,
	License,
	PluginControllerConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
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

	public function __construct() {
		// Need to init these before anything else.
		$this->mod_opts_free;
		$this->mod_opts_pro;
	}

	public function resetToDefaults() {
		$this->mod_opts_free = $this->mod_opts_pro = [];
		$this->delete();
	}

	/**
	 * @param Base\ModCon|mixed $mod
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

	private function key( string $type ) :string {
		return self::con()->prefix( sprintf( 'opts_%s', $type ), '_' );
	}

	public function __get( string $key ) {
		$val = parent::__get( $key );

		if ( $val === null && \preg_match( '#^mod_opts_(all|free|pro)$#', $key, $matches ) ) {
			if ( self::con()->plugin_reset ) {
				$val = [];
			}
			else {
				$val = Services::WpGeneral()->getOption( $this->key( $matches[ 1 ] ) );
				if ( !\is_array( $val ) ) {
					$val = \method_exists( $this, 'flatten' ) ? $this->flatten() : [];
				}
				$this->{$key} = $val;
			}
		}

		return $val;
	}

	public function delete() :void {
		foreach ( [ self::TYPE_ALL, self::TYPE_PRO, self::TYPE_FREE ] as $type ) {
			Services::WpGeneral()->deleteOption( $this->key( $type ) );
		}
	}

	/**
	 * @deprecated 19.0.7
	 */
	public function commit() :void {
		$this->store();
	}

	public function store() {
		$con = self::con();
		if ( !$con->plugin_deleting ) {
			add_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
			$this->preStore();
			$WP = Services::WpGeneral();
			$updated = false;
			foreach ( [ self::TYPE_PRO, self::TYPE_FREE ] as $type ) {
				$updated = $WP->updateOption( $this->key( $type ), $this->{'mod_opts_'.$type} ) || $updated;
			}
			if ( $updated && \method_exists( $this, 'flatten' ) ) {
				$WP->updateOption( $this->key( self::TYPE_ALL ), $this->flatten() );
			}
			$this->postStore();
			remove_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		}
	}

	private function flatten() :array {
		$toStore = [
			'version' => self::con()->cfg->version(),
			'opts'    => [
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
					\sort( $optValue );
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
				if ( \is_scalar( $optValue ) ) {
					if ( $optValue !== $flatFree[ $optKey ] ) {
						$store = true;
					}
				}
				elseif ( \is_array( $optValue ) ) {
					\sort( $optValue );
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

		$toStore[ 'opts' ][ self::TYPE_FREE ] = $flatFree;
		$toStore[ 'opts' ][ self::TYPE_PRO ] = $flatPro;

		return \array_merge( $toStore, $extras );
	}

	private function preStore() {
		$con = self::con();

		// Pre-process options.
		foreach ( self::con()->modules as $mod ) {
			$mod->opts()->preSave();
		}

		do_action( $con->prefix( 'pre_options_store' ) );

		$type = $con->isPremiumActive() ? self::TYPE_PRO : self::TYPE_FREE;
		$latest = $this->{'mod_opts_'.$type};
		$stored = Services::WpGeneral()->getOption( $this->key( $type ) );
		$hasDiff = false;
		$diffs = [];

		$strings = @\class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions' ) ?
			new StringsOptions() : null;

		foreach ( \array_intersect_key( $latest, $con->modules ) as $slug => $options ) {
			$mod = $con->modules[ $slug ];
			$opts = $mod->opts();
			$hidden = \array_keys( $opts->getHiddenOptions() );
			foreach ( $options as $opt => $optValue ) {

				if ( \serialize( $optValue ) !== \serialize( $stored[ $slug ][ $opt ] ?? null ) ) {

					$hasDiff = true;

					if ( !\in_array( $opt, $hidden ) ) {
						if ( $opts->getOptionType( $opt ) === 'checkbox' ) {
							$optValue = $optValue === 'Y' ? 'on' : 'off';
						}
						elseif ( !\is_scalar( $optValue ) ) {
							switch ( $opts->getOptionType( $opt ) ) {
								case 'array':
								case 'multiple_select':
									$optValue = \implode( ', ', $optValue );
									break;
								default:
									$optValue = sprintf( '%s (JSON Encoded)', \json_encode( $optValue ) );
									break;
							}
						}
						try {
							$diffs[ $opt ] = [
								'name'  => ( empty( $strings ) ?
									$mod->getStrings()->getOptionStrings( $opt ) : $strings->getFor( $opt ) )[ 'name' ],
								'key'   => $opt,
								'value' => $optValue,
							];
						}
						catch ( \Exception $e ) {
						}
					}
				}
			}
		}

		foreach ( $diffs as $params ) {
			self::con()->fireEvent( 'plugin_option_changed', [
				'audit_params' => $params
			] );
		}

		do_action( $con->prefix( 'after_pre_options_store' ), $hasDiff );
	}

	private function postStore() {
		foreach ( self::con()->modules as $mod ) {
			$mod->opts()->resetChangedOpts();
		}
	}
}
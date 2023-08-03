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
 */
class OptsHandler extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const TYPE_FREE = 'free';
	public const TYPE_PRO = 'pro';

	public function __construct() {
		// Need to init these before anything else.
		$this->mod_opts_free;
		$this->mod_opts_pro;
	}

	public function resetToDefaults() {
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

		if ( !\in_array( $type, [ self::TYPE_PRO, self::TYPE_FREE ], true ) ) {
			if ( $mod->cfg->slug === License\ModCon::SLUG ) {
				$type = self::TYPE_FREE;
			}
			else {
				$type = self::con()->isPremiumActive() ? self::TYPE_PRO : self::TYPE_FREE;
			}
		}
		elseif ( $type === self::TYPE_PRO && $mod->cfg->slug === License\ModCon::SLUG ) {
			$type = self::TYPE_FREE;
		}

		$opts = $mod->opts();

		if ( $type === self::TYPE_FREE ) {
			foreach ( $mod->cfg->options as $opt ) {
				if ( ( $opt[ 'premium' ] ?? false ) && $values[ $opt[ 'key' ] ] !== $opts->getOptDefault( $opt[ 'key' ] ) ) {
					$values[ $opt[ 'key' ] ] = $opts->getOptDefault( $opt[ 'key' ] );
				}
			}
		}

		$allOptsValues = $this->{'mod_opts_'.$type};
		$allOptsValues[ $mod->cfg->slug ] = $values;
		$this->{'mod_opts_'.$type} = $allOptsValues;

		if ( $type === self::TYPE_PRO ) {
			$this->setFor( $mod, $values, self::TYPE_FREE );
		}

		return $this;
	}

	private function key( string $type ) :string {
		return self::con()->prefix( sprintf( 'opts_%s_%s', $type, \substr( \sha1( \get_class() ), 0, 6 ) ), '_' );
	}

	public function __get( string $key ) {
		$val = parent::__get( $key );
		if ( \in_array( $key, [ 'mod_opts_free', 'mod_opts_pro' ] ) && !\is_array( $val ) ) {
			$type = \str_replace( 'mod_opts_', '', $key );
			$val = Services::WpGeneral()->getOption( $this->key( $type ) );
			if ( !\is_array( $val ) ) {
				$val = $key === 'mod_opts_pro' ? $this->mod_opts_free : [];
			}
			$this->{$key} = $val;
		}
		return $val;
	}

	public function commit() :void {
		foreach ( self::con()->modules as $module ) {
			$module->saveModOptions( false, false );
		}
		$this->store();
	}

	public function store() {
		add_filter( $this->con()->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );

		$this->preStore();

		foreach ( [ self::TYPE_PRO, self::TYPE_FREE ] as $type ) {
			Services::WpGeneral()->updateOption( $this->key( $type ), $this->{'mod_opts_'.$type} );
		}

		remove_filter( $this->con()->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
	}

	private function preStore() {
		$con = self::con();
		do_action( $con->prefix( 'pre_options_store' ) );

		$type = $con->isPremiumActive() ? self::TYPE_PRO : self::TYPE_FREE;
		$latest = $this->{'mod_opts_'.$type};
		$stored = Services::WpGeneral()->getOption( $this->key( $type ) );
		$diffs = [];
		foreach ( $latest as $slug => $options ) {
			$mod = $con->modules[ $slug ];
			$opts = $mod->opts();
			$hidden = \array_keys( $opts->getHiddenOptions() );
			foreach ( $options as $optKey => $optValue ) {
				if ( !\in_array( $optKey, $hidden )
					 && \serialize( $optValue ) !== \serialize( $stored[ $slug ][ $optKey ] ?? null )
				) {
					if ( $opts->getOptionType( $optKey ) === 'checkbox' ) {
						$optValue = $optValue === 'Y' ? 'on' : 'off';
					}
					elseif ( \is_array( $optValue ) ) {
						$optValue = \implode( ', ', $optValue );
					}
					try {
						$diffs[ $optKey ] = [
							'name'  => $mod->getStrings()->getOptionStrings( $optKey )[ 'name' ],
							'key'   => $optKey,
							'value' => $optValue,
						];
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}

		foreach ( $diffs as $params ) {
			$this->con()->fireEvent( 'plugin_option_changed', [
					'audit_params' => $params
				]
			);
		}
	}
}
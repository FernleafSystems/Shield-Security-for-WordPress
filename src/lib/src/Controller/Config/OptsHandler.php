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
			if ( !empty( $premiumOpts ) ) {
				$opts = $premiumOpts;
			}
		}
		return $opts;
	}

	/**
	 * @param Base\ModCon|mixed $mod
	 */
	public function setFor( $mod, ?string $type = null ) :void {

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
		$thisModValues = $opts->getAllOptionsValues();

		if ( $type === self::TYPE_FREE ) {
			foreach ( $mod->cfg->options as $opt ) {
				if ( ( $opt[ 'premium' ] ?? false ) && $thisModValues[ $opt[ 'key' ] ] !== $opts->getOptDefault( $opt[ 'key' ] ) ) {
					$thisModValues[ $opt[ 'key' ] ] = $opts->getOptDefault( $opt[ 'key' ] );
				}
			}
		}

		$allOptsValues = $this->{'mod_opts_'.$type};
		$allOptsValues[ $mod->cfg->slug ] = $thisModValues;
		$this->{'mod_opts_'.$type} = $allOptsValues;

		Services::WpGeneral()->updateOption( $this->key( $type ), $this->{'mod_opts_'.$type} );

		if ( $type === self::TYPE_PRO ) {
			$this->setFor( $mod, self::TYPE_FREE );
		}
	}

	private function key( string $type ) :string {
		return sprintf( 'aptoweb_shield_opts_%s_%s', $type, \substr( \sha1( \get_class() ), 0, 6 ) );
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
}
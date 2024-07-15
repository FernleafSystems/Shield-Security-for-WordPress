<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = \FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules::IPS;

	/**
	 * @deprecated 19.2
	 */
	public function loadOffenseTracker() :Lib\OffenseTracker {
		return self::con()->comps->offense_tracker;
	}

	/**
	 * @deprecated 19.2
	 */
	public function getAllowable404s() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_ext_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_extensions_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext ) && \preg_match( '#^[a-z\d]+$#i', $ext );
			}
		) );
	}

	/**
	 * @deprecated 19.2
	 */
	public function getAllowableScripts() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_invalid_scripts' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_invalid_scripts', $def ),
			function ( $script ) {
				return !empty( $script ) && \is_string( $script ) && \strpos( $script, '.php' );
			}
		) );
	}
}
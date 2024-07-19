<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class FireEventsForChangedOpts {

	use PluginControllerConsumer;

	public function run( array $changes ) {
		if ( !empty( $changes ) ) {

			$strings = new StringsOptions();
			foreach ( $changes as $opt => $oldValue ) {
				$optDef = self::con()->cfg->configuration->options[ $opt ] ?? null;
				if ( empty( $optDef ) || $optDef[ 'section' ] === 'section_hidden' ) {
					continue;
				}

				$logValue = self::con()->opts->optGet( $opt );

				if ( $optDef[ 'type' ] === 'checkbox' ) {
					$logValue = $logValue === 'Y' ? 'on' : 'off';
				}
				elseif ( !\is_scalar( $logValue ) ) {
					switch ( $optDef[ 'type' ] ) {
						case 'array':
						case 'multiple_select':
							$logValue = \implode( ', ', $logValue );
							break;
						default:
							$logValue = sprintf( '%s (JSON Encoded)', \wp_json_encode( $logValue ) );
							break;
					}
				}
				try {
					self::con()->fireEvent( 'plugin_option_changed', [
						'audit_params' => [
							'name'  => $strings->getFor( $opt )[ 'name' ],
							'key'   => $opt,
							'value' => $logValue,
						]
					] );
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}
}
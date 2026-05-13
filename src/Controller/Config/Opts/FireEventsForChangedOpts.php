<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class FireEventsForChangedOpts {

	use PluginControllerConsumer;

	public function run( array $changes ) {
		if ( !empty( $changes ) ) {

			$strings = new StringsOptions();
			$valueFormatter = new OptionAuditValueFormatter();
			foreach ( $changes as $opt => $oldValue ) {
				$optDef = self::con()->cfg->configuration->options[ $opt ] ?? null;
				if ( empty( $optDef ) || $optDef[ 'section' ] === 'section_hidden' ) {
					continue;
				}

				$logValue = $valueFormatter->format( $optDef, self::con()->opts->optGet( $opt ) );
				try {
					self::con()->comps->events->fireEvent( 'plugin_option_changed', [
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

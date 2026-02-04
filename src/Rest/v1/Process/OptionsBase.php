<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class OptionsBase extends Base {

	protected function getAllOptions() :array {
		$config = self::con()->cfg->configuration;
		$opts = self::con()->opts;

		$all = [];
		foreach ( ( new Export() )->getRawOptionsExport() as $optKey ) {
			if ( $opts->optExists( $optKey ) ) {
				$all[] = [
					'key'    => $optKey,
					'value'  => $opts->optGet( $optKey ),
					'module' => $config->modFromOpt( $optKey ),
				];
			}
		}
		return $all;
	}

	protected function setOptFromRequest( string $key, $value ) {
		$opts = self::con()->opts;
		if ( $opts->optExists( $key ) ) {
			if ( \is_null( $value ) ) {
				$opts->optReset( $key );
			}
			else {
				/**
				 * It turns out JSON-encoded integers come out as type:double, so we have to convert it,
				 * so we can validate it after the fact using serialize, or we'll get i:0 vs d:0.
				 */
				if ( $opts->optType( $key ) === 'integer' ) {
					$value = (int)$value;
				}
				$opts->optSet( $key, $value );
			}
		}
	}
}
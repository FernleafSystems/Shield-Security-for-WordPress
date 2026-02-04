<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class OptionsSingleGet extends OptionsBase {

	protected function process() :array {
		$optKey = $this->getWpRestRequest()->get_param( 'key' );

		$theOption = null;
		if ( self::con()->opts->optExists( $optKey ) ) {
			$theOption = [
				'key'    => $optKey,
				'value'  => self::con()->opts->optGet( $optKey ),
				'module' => self::con()->cfg->configuration->modFromOpt( $optKey ),
			];
		}

		return [
			'options' => $theOption
		];
	}
}
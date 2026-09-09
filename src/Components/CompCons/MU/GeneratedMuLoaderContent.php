<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GeneratedMuLoaderContent {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() :string {
		$templateFile = \rtrim( __DIR__, '/\\' ).'/.mu-template.txt';
		if ( !\is_readable( $templateFile ) ) {
			throw new \Exception( sprintf( __( "Couldn't read MU plugin template from %s", 'wp-simple-firewall' ), $templateFile ) );
		}

		$template = \file_get_contents( $templateFile );
		if ( !\is_string( $template ) || $template === '' ) {
			throw new \Exception( sprintf( __( "Couldn't read MU plugin template from %s", 'wp-simple-firewall' ), $templateFile ) );
		}

		$replacements = $this->replacements();
		return \str_replace( \array_keys( $replacements ), \array_values( $replacements ), $template );
	}

	/**
	 * @return array<string,string>
	 */
	private function replacements() :array {
		$con = self::con();
		return [
			'SHIELD_ROOT_FILE'     => $con->getRootFile(),
			'SHIELD_PLUGIN_NAME'   => $con->labels->Name,
			'SHIELD_PLUGIN_URL'    => $con->labels->PluginURI,
			'SHIELD_PLUGIN_AUTHOR' => $con->labels->Author,
		];
	}
}

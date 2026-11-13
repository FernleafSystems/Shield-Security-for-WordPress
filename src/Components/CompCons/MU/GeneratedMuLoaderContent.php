<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GeneratedMuLoaderContent {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function build() :string {
		$templateFile = \rtrim( __DIR__, '/\\' ).'/mu-template.txt';
		if ( !\is_readable( $templateFile ) ) {
			throw new \Exception( sprintf( __( "Couldn't read MU plugin template from %s", 'wp-simple-firewall' ), $templateFile ) );
		}

		$template = \file_get_contents( $templateFile );
		if ( !\is_string( $template ) || $template === '' ) {
			throw new \Exception( sprintf( __( "Couldn't read MU plugin template from %s", 'wp-simple-firewall' ), $templateFile ) );
		}

		// strtr() replaces each placeholder once, so a value that contains another placeholder's name stays as it is.
		return \strtr( $template, $this->replacements() );
	}

	/**
	 * The name, URL and author are white-label settings, and this content is written to a PHP file that loads on
	 * every request. Each value is made safe for where the template puts it, so a value can't break that file.
	 * @return array<string,string>
	 */
	private function replacements() :array {
		$con = self::con();
		$name = $con->labels->Name;
		return [
			'SHIELD_ROOT_FILE_PHP_STRING'  => $this->forPhpString( $con->getRootFile() ),
			// The removal notice's text is a sprintf() format, so a "%" in the name must be doubled to print as itself.
			'SHIELD_PLUGIN_NAME_IN_NOTICE' => $this->forPhpString( \str_replace( '%', '%%', $name ) ),
			'SHIELD_PLUGIN_NAME'           => $this->forHeaderComment( $name ),
			'SHIELD_PLUGIN_URL'            => $this->forHeaderComment( (string)$con->labels->PluginURI ),
			'SHIELD_PLUGIN_AUTHOR'         => $this->forHeaderComment( (string)$con->labels->Author ),
		];
	}

	/**
	 * For a single-quoted PHP string, only a quote and a backslash in front of a quote, another backslash or the
	 * closing quote need escaping. Escaping nothing else keeps an existing loader file, such as one with a Windows
	 * path, the same as the content built now, which is how the generated loader is recognised.
	 */
	private function forPhpString( string $value ) :string {
		return \str_replace( "'", "\\'", (string)\preg_replace( "#\\\\(?=[\\\\']|$)#", '\\\\\\\\', $value ) );
	}

	/**
	 * In the plugin header comment, a "*\/" would close the comment and a line break would start a new header line.
	 */
	private function forHeaderComment( string $value ) :string {
		return \str_replace( [ '*/', "\r", "\n" ], [ '* /', ' ', ' ' ], $value );
	}
}

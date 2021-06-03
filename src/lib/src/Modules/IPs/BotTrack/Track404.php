<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class Track404 extends Base {

	const OPT_KEY = 'track_404';

	protected function process() {
		add_action( 'template_redirect', function () {
			if ( is_404() ) {
				$reqPath = $this->getRequestPath();
				$extensions = implode( '|', $this->getAllowableExtensions() );

				// if the request's file extension is allowed to trigger 404s, we fire only the event, without transgression.
				// However, if the requested asset is within for a plugin or theme that doesn't exists, it's not allowed.
				$allowed = preg_match( sprintf( '#\.(%s)$#i', $extensions ), $reqPath ) === 1
						   && !$this->isRequestToInvalidPlugin()
						   && !$this->isRequestToInvalidTheme();

				$this->doTransgression( $allowed );
			}
		} );
	}

	private function isRequestToInvalidPlugin() :bool {
		$isInvalid = false;

		$reqPath = $this->getRequestPath();
		$pathToPlugins = ltrim( wp_parse_url( plugins_url(), PHP_URL_PATH ), '/' );

		if ( strpos( $reqPath, $pathToPlugins ) === 0 ) {
			$assetStub = trim( str_replace( $pathToPlugins, '', $reqPath ), '/' );
			if ( substr_count( $assetStub, '/' ) > 0 ) {
				$dir = explode( '/', $assetStub, 2 )[ 0 ];
				$file = Services::WpPlugins()->findPluginFileFromDirName( $dir );
				if ( empty( $file ) ) {
					$isInvalid = true;
				}
			}
		}

		return $isInvalid;
	}

	private function isRequestToInvalidTheme() :bool {
		$isInvalid = false;

		$reqPath = $this->getRequestPath();
		$pathsToThemes = ltrim( dirname( wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH ) ), '/' );

		if ( strpos( $reqPath, $pathsToThemes ) === 0 ) {
			$assetStub = trim( str_replace( $pathsToThemes, '', $reqPath ), '/' );
			if ( substr_count( $assetStub, '/' ) > 0 ) {
				$dir = explode( '/', $assetStub, 2 )[ 0 ];
				$isInvalid = !Services::WpThemes()->getExists( $dir );
			}
		}

		return $isInvalid;
	}

	private function getAllowableExtensions() :array {
		$defExts = $this->getOptions()->getDef( 'allowable_ext_404s' );
		$extensions = apply_filters( 'shield/allowable_extensions_404s', $defExts );
		return is_array( $extensions ) ? $extensions : $defExts;
	}

	private function getRequestPath() :string {
		return ltrim( Services::Request()->getPath(), '/' );
	}
}

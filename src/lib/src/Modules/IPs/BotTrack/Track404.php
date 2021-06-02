<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

class Track404 extends Base {

	const OPT_KEY = 'track_404';

	protected function process() {
		add_action( 'template_redirect', function () {
			if ( is_404() ) {
				// if the request's file extension is allowed to trigger 404s, we only fire the event, without transgression
				$extensions = implode( '|', $this->getAllowableExtensions() );
				$this->doTransgression(
					preg_match( sprintf( '#\.(%s)$#i', $extensions ), Services::Request()->getPath() ) === 1
				);
			}
		} );
	}

	private function getAllowableExtensions() :array {
		$defExts = $this->getOptions()->getDef( 'allowable_ext_404s' );
		$extensions = apply_filters( 'shield/allowable_extensions_404s', $defExts );
		return is_array( $extensions ) ? $extensions : $defExts;
	}
}

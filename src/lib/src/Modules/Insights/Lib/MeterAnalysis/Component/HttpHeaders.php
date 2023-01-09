<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers\Options;

class HttpHeaders extends Base {

	public const SLUG = 'http_headers';
	public const WEIGHT = 10;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Headers();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled()
			   && $opts->isEnabledXFrame()
			   && $opts->isEnabledXssProtection()
			   && $opts->isEnabledContentTypeHeader()
			   && $opts->isReferrerPolicyEnabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Comments();
		return $mod->isModOptEnabled() ? $this->link( 'section_security_headers' ) : $this->link( 'enable_headers' );
	}

	public function title() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Important HTTP Headers are helping to protect visitors.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Important HTTP Headers aren't being used to help protect visitors.", 'wp-simple-firewall' );
	}
}
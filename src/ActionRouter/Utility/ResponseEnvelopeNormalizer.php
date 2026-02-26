<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

class ResponseEnvelopeNormalizer {

	public static function forAjax( array $payload ) :array {
		return \array_merge(
			[
				'success'     => false,
				'page_reload' => false,
				'message'     => __( 'No AJAX message provided', 'wp-simple-firewall' ),
				'error'       => '',
				'html'        => '',
			],
			$payload
		);
	}

	public static function forAjaxAdapter( array $payload, string $fallbackMessage = '', string $fallbackError = '' ) :array {
		return \array_merge(
			[
				'success'    => false,
				'message'    => $fallbackMessage,
				'error'      => $fallbackError,
				'html'       => '-',
				'page_title' => '-',
				'page_url'   => '-',
				'show_toast' => true,
			],
			$payload
		);
	}

	public static function forRestProcess( array $payload ) :array {
		return \array_merge(
			[
				'page_reload' => false,
				'message'     => '',
				'html'        => '',
			],
			$payload
		);
	}

	public static function forBatchSubresponse( array $payload ) :array {
		return self::forAjax( $payload );
	}
}

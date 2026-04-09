<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

class ResponseEnvelopeNormalizer {

	public static function forAjax( array $payload ) :array {
		return \array_merge(
			self::ajaxBaseDefaults(
				__( 'No AJAX message provided', 'wp-simple-firewall' ),
				'',
				''
			),
			[
				'page_reload' => false,
			],
			$payload
		);
	}

	public static function forAjaxAdapter( array $payload, string $fallbackMessage = '', string $fallbackError = '' ) :array {
		return \array_merge(
			self::ajaxBaseDefaults(
				$fallbackMessage,
				$fallbackError,
				'-'
			),
			[
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

	public static function forAjaxAuthRefresh() :array {
		$message = __( 'Your WordPress session has expired. Reloading the page now.', 'wp-simple-firewall' );

		return self::forAjax( [
			'success'               => false,
			'page_reload'           => true,
			'auth_refresh_required' => true,
			'show_toast'            => false,
			'error_code'            => 'user_auth_required',
			'message'               => $message,
			'error'                 => $message,
		] );
	}

	private static function ajaxBaseDefaults( string $message, string $error, string $html ) :array {
		return [
			'success' => false,
			'message' => $message,
			'error'   => $error,
			'html'    => $html,
		];
	}
}

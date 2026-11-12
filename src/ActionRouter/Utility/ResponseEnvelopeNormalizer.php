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

	/**
	 * What a logged-in user's AJAX request is told when its action nonce fails. The page's nonces belong to a
	 * login session that has since changed, or the page has been open too long; either way it must be reloaded.
	 * The page shows the message in a dialog and reloads when it's closed; a page without the dialog shows the
	 * message as it would any other failure.
	 */
	public static function forAjaxSessionChanged() :array {
		$message = __( 'Your session has changed since this page was loaded. Reload the page to continue.', 'wp-simple-firewall' );

		return self::forAjax( [
			'success'         => false,
			'session_changed' => true,
			'error_code'      => 'session_changed',
			'message'         => $message,
			'error'           => $message,
			'dialog'          => [
				'title'         => __( 'Session Changed', 'wp-simple-firewall' ),
				'confirm_label' => __( 'Reload', 'wp-simple-firewall' ),
			],
		] );
	}

	/**
	 * What an AJAX request is told when its action nonce fails: a logged-out request sent alone, or any item in a
	 * batch. A logged-in request sent alone is told its session changed; see forAjaxSessionChanged().
	 */
	public static function nonceFailedMessage() :string {
		return __( 'Nonce Failed.', 'wp-simple-firewall' );
	}

	/**
	 * What an AJAX request is told when its action needs a Security Admin, whether sent alone or in a batch.
	 */
	public static function securityAdminRequiredMessage() :string {
		return \implode( ' ', [
			__( 'You must be authorised as a Security Admin to perform this action.', 'wp-simple-firewall' ),
			__( 'You may need to reload this page to continue.', 'wp-simple-firewall' ),
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

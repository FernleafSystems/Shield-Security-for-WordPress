<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Utilities\Data\CaptureOutput;
use FernleafSystems\Wordpress\Services\Services;

class WpLoginReplicaHeader extends Base {

	const SLUG = 'render_shield_wploginreplica_header';
	const TEMPLATE = '/components/wplogin_replica/login_header.twig';

	protected function getRenderData() :array {
		global $interim_login, $action;

		// Don't index any of these forms.
		if ( function_exists( 'wp_robots_sensitive_page' ) ) {
			add_filter( 'wp_robots', 'wp_robots_sensitive_page' );
		}
		if ( function_exists( 'wp_strict_cross_origin_referrer' ) ) {
			add_action( 'login_head', 'wp_strict_cross_origin_referrer' );
		}

		$wp_error = $this->action_data[ 'wp_error' ] ?? null;
		if ( !is_wp_error( $wp_error ) ) {
			$wp_error = new \WP_Error();
		}

		if ( function_exists( 'wp_login_viewport_meta' ) ) {
			add_action( 'login_head', 'wp_login_viewport_meta' );
		}

		$login_title = get_bloginfo( 'name', 'display' );

		/* translators: Login screen title. 1: Login screen name, 2: Network or site name. */
		$login_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $this->action_data[ 'title' ], $login_title );

		/**
		 * Filters the title tag content for login page.
		 *
		 * @since 4.9.0
		 *
		 * @param string $login_title The page title, with extra context added.
		 * @param string $title       The original page title.
		 */
		$login_title = apply_filters( 'login_title', $login_title, $this->action_data[ 'title' ] );

		wp_enqueue_style( 'login' );

		/**
		 * Enqueue scripts and styles for the login page.
		 *
		 * @since 3.1.0
		 */
		$actionLoginEnqScripts = CaptureOutput::Capture( function () {
			do_action( 'login_enqueue_scripts' );
		} );

		/**
		 * Fires in the login page header after scripts are enqueued.
		 *
		 * @since 2.1.0
		 */
		$actionLoginHead = CaptureOutput::Capture( function () {
			do_action( 'login_head' );
		} );

		$login_header_url = __( 'https://wordpress.org/' );

		/**
		 * Filters link URL of the header logo above login form.
		 *
		 * @since 2.1.0
		 *
		 * @param string $login_header_url Login header logo URL.
		 */
		$login_header_url = apply_filters( 'login_headerurl', $login_header_url );

		$login_header_title = '';

		$login_header_text = empty( $login_header_title ) ? __( 'Powered by WordPress' ) : $login_header_title;

		/**
		 * Filters the link text of the header logo above the login form.
		 *
		 * @since 5.2.0
		 *
		 * @param string $login_header_text The login header logo link text.
		 */
		$login_header_text = apply_filters( 'login_headertext', $login_header_text );

		$classes = [ 'login-action-'.Services::Request()->request( 'action', 'login' ), 'wp-core-ui' ];

		if ( is_rtl() ) {
			$classes[] = 'rtl';
		}

		if ( $interim_login ) {
			$classes[] = 'interim-login';
		}

		if ( 'success' === $interim_login ) {
			$classes[] = 'interim-login-success';
		}

		$classes[] = ' locale-'.sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

		/**
		 * Filters the login page body classes.
		 *
		 * @since 3.5.0
		 *
		 * @param string[] $classes An array of body classes.
		 * @param string   $action  The action that brought the visitor to the login page.
		 */
		$classes = apply_filters( 'login_body_class', $classes, $action );

		$actionLoginHeader = CaptureOutput::Capture( function () {
			/**
			 * Fires in the login page header after the body tag is opened.
			 *
			 * @since 4.6.0
			 */
			do_action( 'login_header' );
		} );

		/**
		 * Filters the message to display above the login form.
		 *
		 * @since 2.1.0
		 *
		 * @param string $message Login message text.
		 */
		$message = apply_filters( 'login_message', $this->action_data[ 'message' ] ?? '' );
		if ( !empty( $message ) ) {
			echo $message."\n";
		}

		$loginErrors = '';
		$loginMessages = '';
		if ( $wp_error->has_errors() ) {
			$errors = '';
			$messages = '';

			foreach ( $wp_error->get_error_codes() as $code ) {
				$severity = $wp_error->get_error_data( $code );
				foreach ( $wp_error->get_error_messages( $code ) as $error_message ) {
					if ( 'message' === $severity ) {
						$messages .= '	'.$error_message."<br />\n";
					}
					else {
						$errors .= '	'.$error_message."<br />\n";
					}
				}
			}

			if ( !empty( $errors ) ) {
				/**
				 * Filters the error messages displayed above the login form.
				 *
				 * @since 2.1.0
				 *
				 * @param string $errors Login error message.
				 */
				$loginErrors = apply_filters( 'login_errors', $errors );
			}

			if ( !empty( $messages ) ) {
				/**
				 * Filters instructional messages displayed above the login form.
				 *
				 * @since 2.5.0
				 *
				 * @param string $messages Login messages.
				 */
				$loginMessages = apply_filters( 'login_messages', $messages );
			}
		}

		$interimMessage = $this->action_data[ 'interim_message' ] ?? '';

		return [
			'content' => [
				'action_login_enqueue_scripts' => $actionLoginEnqScripts,
				'action_login_head'            => $actionLoginHead,
				'action_login_header'          => $actionLoginHeader,
				'meta_content_type'            => get_bloginfo( 'html_type' ),
				'meta_charset'                 => get_bloginfo( 'charset' ),
				'login_errors'                 => $loginErrors,
				'login_messages'               => $loginMessages,
			],
			'flags'   => [
				'has_login_errors'    => !empty( $loginErrors ),
				'has_login_messages'  => !empty( $loginMessages ),
				'has_interim_message' => $interim_login && !empty( $interimMessage ),
				'is_interim_login'    => $interim_login,
			],
			'hrefs'   => [
				'login_header_url' => esc_url( $login_header_url ),
			],
			'strings' => [
				'login_header_text' => $login_header_text,
				'login_title'       => $login_title,
				'message'           => $message,
				'interim_message'   => $interimMessage,
			],
			'vars'    => [
				'language_attributes' => get_language_attributes( 'html' ),
				'meta_content_type'   => get_bloginfo( 'html_type' ),
				'meta_charset'        => get_bloginfo( 'charset' ),
				'classes_body'        => esc_attr( implode( ' ', $classes ) ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'title'
		];
	}
}
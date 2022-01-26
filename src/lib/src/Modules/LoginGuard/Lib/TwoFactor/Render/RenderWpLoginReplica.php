<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Render;

use FernleafSystems\Utilities\Data\CaptureOutput;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property bool $include_body
 */
class RenderWpLoginReplica extends RenderBase {

	public function __construct() {
		$this->include_body = true;
	}

	protected function buildPage() :string {
		$this->preRenderSetup();
		return $this->getMod()->renderTemplate( '/components/wplogin_replica/wp_login.twig', [
			'content' => [
				'header' => $this->renderLoginHeader( __( 'Login 2FA Verification', 'wp-simple-firewall' ) ),
				'body'   => $this->include_body ? $this->renderLoginBody() : '',
				'footer' => $this->renderLoginFooter(),
			]
		] );
	}

	private function preRenderSetup() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/login2fa';
			$enqueues[ Enqueue::CSS ][] = 'shield/login2fa';
			return $enqueues;
		} );
	}

	private function renderLoginBody() :string {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$WP = Services::WpGeneral();
		$user = $this->getWpUser();

		global $interim_login;

		return $this->getMod()->renderTemplate( '/components/wplogin_replica/login_body.twig',

			Services::DataManipulation()->mergeArraysRecursive(
				$mod->getUIHandler()->getBaseDisplayData(),
				$this->getCommonFormData(),
				[
					'content' => [
						'providers' => array_filter( array_map(
							function ( $provider ) use ( $user ) {
								return $provider->setUser( $user )->renderFormFieldForWpLogin();
							},
							$mod->getMfaController()->getProvidersForUser( $user, true )
						) )
					],
					'flags'   => [
						'has_error_msg'    => !empty( $this->msg_error ),
						'is_interim_login' => (bool)$interim_login,
					],
					'hrefs'   => [
						'home'        => $WP->getHomeUrl(),
					],
					'strings' => [
						'error_msg'     => $this->msg_error,
						'back_home'     => __( 'Go Back Home', 'wp-simple-firewall' ),
						'button_submit' => __( 'Complete Login', 'wp-simple-firewall' )
					],
				]
			)
		);
	}

	private function renderLoginFooter() :string {
		/**
		 * Fires in the login page footer.
		 *
		 * @since 3.1.0
		 */
		$actionLoginFooter = CaptureOutput::Capture( function () {
			do_action( 'login_footer' );
		} );

		return $this->getMod()->renderTemplate( '/components/wplogin_replica/login_footer.twig', [
			'content' => [
				'action_login_footer' => $actionLoginFooter,
			],
		] );
	}

	private function renderLoginHeader( string $title = 'Log In', string $message = '', $wp_error = null ) :string {
		global $interim_login, $action;

		// Don't index any of these forms.
		add_filter( 'wp_robots', 'wp_robots_sensitive_page' );
		add_action( 'login_head', 'wp_strict_cross_origin_referrer' );

		if ( !is_wp_error( $wp_error ) ) {
			$wp_error = new \WP_Error();
		}

		if ( function_exists( 'wp_login_viewport_meta' ) ) {
			add_action( 'login_head', 'wp_login_viewport_meta' );
		}

		$login_title = get_bloginfo( 'name', 'display' );

		/* translators: Login screen title. 1: Login screen name, 2: Network or site name. */
		$login_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $login_title );

		/**
		 * Filters the title tag content for login page.
		 *
		 * @since 4.9.0
		 *
		 * @param string $login_title The page title, with extra context added.
		 * @param string $title       The original page title.
		 */
		$login_title = apply_filters( 'login_title', $login_title, $title );

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
		$message = apply_filters( 'login_message', $message );
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

		return $this->getMod()->renderTemplate( '/components/wplogin_replica/login_header.twig', [
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
				'has_interim_message' => $interim_login && !empty( $this->interim_message ),
				'is_interim_login'    => $interim_login,
			],
			'hrefs'   => [
				'login_header_url' => esc_url( $login_header_url ),
			],
			'strings' => [
				'login_header_text' => $login_header_text,
				'login_title'       => $login_title,
				'message'           => $message,
				'interim_message'   => $this->interim_message ?? '',
			],
			'vars'    => [
				'language_attributes' => get_language_attributes( 'html' ),
				'meta_content_type'   => get_bloginfo( 'html_type' ),
				'meta_charset'        => get_bloginfo( 'charset' ),
				'classes_body'        => esc_attr( implode( ' ', $classes ) ),
			]
		] );
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	AdminTwoFactorCoverage,
	EmailDeliveryVerification
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	AbstractShieldProvider,
	Email,
	GoogleAuth,
	Passkey,
	Yubikey
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCompatibilityCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\AdminTwoFactor;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * The "administrators without two-factor authentication" to-do, opened from its row in the Actions Queue: whether
 * the account of the person looking is protected, the administrators whose accounts aren't, and which two-factor
 * methods this site has enabled. What it says of the person looking is for them alone, and counts towards nothing.
 *
 * @phpstan-type Viewer array{is_protected:bool, title:string, summary:string, setup_href:string}
 * @phpstan-type Admin array{name:string, login:string, is_viewer:bool, last_login:string, href:string}
 * @phpstan-type Method array{
 *   name:string, example:string, icon_class:string, is_enabled:bool, is_recommended:bool, status:string,
 *   is_pro_only:bool, configure_href:string, configure_label:string
 * }
 */
class AdminTwoFactorDetails extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_admin_two_factor';

	/**
	 * Each method with the setting that enables it and its icon, in the order they're listed.
	 */
	private const METHODS = [
		GoogleAuth::class => [ 'enable_google_authenticator', 'bi bi-phone' ],
		Email::class      => [ 'enable_email_authentication', 'bi bi-envelope' ],
		Yubikey::class    => [ 'enable_yubikey', 'bi bi-key' ],
		Passkey::class    => [ 'enable_passkeys', 'bi bi-fingerprint' ],
	];

	protected function buildCanvasTitle() :string {
		return ( new AdminTwoFactor() )->title();
	}

	protected function buildCanvasBody() :string {
		$coverage = new AdminTwoFactorCoverage();
		$administrators = $coverage->administrators();
		$unprotected = $coverage->unprotectedAmong( $administrators );
		// The people listed are loaded together, and nobody is given a record for being listed.
		self::con()->user_metas->preload( $unprotected );
		$viewer = Services::WpUsers()->getCurrentWpUser();

		return self::con()
				   ->comps
				   ->render
				   ->setTemplate( '/wpadmin/components/actions_queue/admin_two_factor_drawer.twig' )
				   ->setData( [
					   'strings' => [
						   'unprotected' => __( 'Administrators without two-factor authentication', 'wp-simple-firewall' ),
						   'tally'       => sprintf(
							   /* translators: %1$s: number of administrators without two-factor authentication, %2$s: number of administrators */
							   __( '%1$s of %2$s', 'wp-simple-firewall' ),
							   \count( $unprotected ),
							   \count( $administrators )
						   ),
						   'config'      => __( 'Configuration status', 'wp-simple-firewall' ),
						   'why'         => __( 'Why it matters', 'wp-simple-firewall' ),
						   'why_text'    => __( "An administrator can change anything on the site. If one administrator's password is guessed or stolen, two-factor authentication is what stops it being used.", 'wp-simple-firewall' ),
						   'you'         => __( 'You', 'wp-simple-firewall' ),
						   'recommended' => __( 'Recommended', 'wp-simple-firewall' ),
						   'set_up'      => __( 'Set up', 'wp-simple-firewall' ),
						   'view_user'   => __( 'View user', 'wp-simple-firewall' ),
						   'pro_only'    => __( 'Pro only', 'wp-simple-firewall' ),
					   ],
					   'vars'    => [
						   'viewer'      => $viewer instanceof \WP_User
							   ? $this->viewer( $viewer, empty( $unprotected ) ) : null,
						   'unprotected' => \array_map(
							   fn( \WP_User $user ) :array => $this->admin( $user, $viewer ),
							   $unprotected
						   ),
						   'methods'     => $this->methods(),
					   ],
				   ] )
				   ->render();
	}

	/**
	 * @return Viewer
	 */
	private function viewer( \WP_User $viewer, bool $allProtected ) :array {
		$mfa = self::con()->comps->mfa;
		$active = $mfa->getProvidersActiveForUser( $viewer );
		$isProtected = !empty( $active );

		// Backup codes only work beside another method, so they aren't named as one.
		$configured = \array_values( \array_map(
			fn( $provider ) :string => $provider->getProviderName(),
			\array_filter( $active, fn( $provider ) :bool => $provider->isProviderStandalone() )
		) );

		if ( !$isProtected ) {
			$title = __( 'Your own account has no two-factor authentication.', 'wp-simple-firewall' );
		}
		elseif ( $allProtected ) {
			$title = ( new AdminTwoFactor() )->descProtected();
		}
		else {
			$title = __( 'Your account is protected.', 'wp-simple-firewall' );
		}

		return [
			'is_protected' => $isProtected,
			'title'        => $title,
			'summary'      => empty( $configured ) ? ''
				/* translators: %l: list of two-factor methods, such as "Email and Yubikey" */
				: wp_sprintf( __( 'You have configured %l.', 'wp-simple-firewall' ), $configured ),
			// Nothing to set up until a method is enabled for this site.
			'setup_href'   => ( $isProtected || empty( $mfa->getProvidersAvailableToUser( $viewer ) ) ) ? '' : $this->setupHref(),
		];
	}

	/**
	 * Where a person sets up two-factor authentication for themselves: the page of its own when the site has one,
	 * or else their profile.
	 */
	private function setupHref() :string {
		$pages = self::con()->opts->optGet( 'mfa_user_setup_pages' );
		if ( \in_array( 'dedicated', $pages, true ) ) {
			$href = URL::Build(
				Services::WpGeneral()->getAdminUrl( current_user_can( 'list_users' ) ? 'users.php' : 'profile.php' ),
				[ 'page' => 'shield-login-security' ]
			);
		}
		elseif ( \in_array( 'profile', $pages, true ) ) {
			$href = Services::WpGeneral()->getAdminUrl( 'profile.php' ).'#ShieldMfaUserProfileForm';
		}
		else {
			$href = '';
		}
		return $href;
	}

	/**
	 * @return Admin
	 */
	private function admin( \WP_User $user, ?\WP_User $viewer ) :array {
		$isViewer = $viewer instanceof \WP_User && $viewer->ID === $user->ID;
		$lastLoginAt = self::con()->user_metas->for( $user )->record->last_login_at;
		return [
			'name'       => $user->display_name,
			// Shown beside the name only where it tells the reader something the name doesn't.
			'login'      => $user->user_login === $user->display_name ? '' : $user->user_login,
			'is_viewer'  => $isViewer,
			'last_login' => $lastLoginAt > 0 ? sprintf(
				/* translators: %s: how long ago, such as "2 days ago" */
				__( 'Last logged in %s', 'wp-simple-firewall' ),
				Services::Request()->carbon( true )->setTimestamp( $lastLoginAt )->diffForHumans()
			) : '',
			// The person looking has their own way in, at the top of the drawer.
			'href'       => $isViewer ? '' : (string)get_edit_user_link( $user->ID ),
		];
	}

	/**
	 * @return list<Method>
	 */
	private function methods() :array {
		$con = self::con();
		$methods = [];
		/** @var class-string<AbstractShieldProvider> $provider */
		foreach ( self::METHODS as $provider => [ $optKey, $iconClass ] ) {
			$isSwitchedOn = $con->opts->optIs( $optKey, 'Y' );
			// Email that nobody is required or allowed to use is enabled in name only.
			$isEmailForNobody = $provider === Email::class && $provider::ProviderEnabled()
								&& empty( $this->rolesRequiredToUseEmail() ) && !$con->opts->optIs( 'email_any_user_set', 'Y' );
			$isEnabled = $provider::ProviderEnabled() && !$isEmailForNobody;

			if ( $isEnabled ) {
				$status = $this->enabledStatus( $provider );
			}
			elseif ( $isEmailForNobody ) {
				$status = __( 'Enabled, but no role is required to use it.', 'wp-simple-firewall' );
			}
			else {
				$status = $isSwitchedOn ? $this->notWorkingStatus( $provider ) : '';
			}

			$methods[] = [
				'name'            => $provider::ProviderName(),
				'example'         => $provider === GoogleAuth::class ? GoogleAuth::ExampleApp() : '',
				'icon_class'      => $iconClass,
				'is_enabled'      => $isEnabled,
				'is_recommended'  => $provider === GoogleAuth::class,
				'status'          => $status,
				'is_pro_only'     => !$con->opts->optHasAccess( $optKey ),
				'configure_href'  => $isEnabled ? ''
					: $con->plugin_urls->cfgForOpt( $isEmailForNobody ? 'two_factor_auth_user_roles' : $optKey ),
				'configure_label' => $isSwitchedOn ? __( 'Open in Configure', 'wp-simple-firewall' ) : __( 'Turn on in Configure', 'wp-simple-firewall' ),
			];
		}
		return $methods;
	}

	/**
	 * Who can use a method this site has enabled. Email is the one method a role can be required to use, and
	 * without Pro it's offered to nobody else.
	 * @param class-string<AbstractShieldProvider> $provider
	 */
	private function enabledStatus( string $provider ) :string {
		$forAll = __( 'Enabled for all users.', 'wp-simple-firewall' );
		$required = $provider === Email::class ? $this->rolesRequiredToUseEmail() : [];
		if ( empty( $required ) ) {
			return $forAll;
		}

		return wp_sprintf(
			self::con()->opts->optIs( 'email_any_user_set', 'Y' )
				/* translators: %l: list of user roles, such as "Administrator and Editor" */
				? __( 'Enabled for all users, and required for %l.', 'wp-simple-firewall' )
				/* translators: %l: list of user roles, such as "Administrator and Editor" */
				: __( 'Enabled, and required for %l.', 'wp-simple-firewall' ),
			$required
		);
	}

	/**
	 * @return list<string> the names of the roles that must use email two-factor authentication
	 */
	private function rolesRequiredToUseEmail() :array {
		$roleNames = wp_roles()->get_names();
		$required = [];
		foreach ( self::con()->comps->opts_lookup->getLoginGuardEmailAuth2FaRoles() as $role ) {
			$required[] = isset( $roleNames[ $role ] ) ? translate_user_role( $roleNames[ $role ] ) : $role;
		}
		return $required;
	}

	/**
	 * Why a method that's switched on in the settings isn't in use.
	 * @param class-string<AbstractShieldProvider> $provider
	 */
	private function notWorkingStatus( string $provider ) :string {
		$opts = self::con()->opts;
		$status = '';
		switch ( $provider ) {
			case Email::class:
				if ( ( new EmailDeliveryVerification() )->status() !== EmailDeliveryVerification::STATUS_VERIFIED ) {
					$status = __( 'Waiting for email delivery to be verified.', 'wp-simple-firewall' );
				}
				break;
			case Yubikey::class:
				if ( empty( $opts->optGet( 'yubikey_app_id' ) ) || empty( $opts->optGet( 'yubikey_api_key' ) ) ) {
					$status = __( 'The Yubico application credentials are incomplete.', 'wp-simple-firewall' );
				}
				break;
			case Passkey::class:
				if ( !( new PasskeyCompatibilityCheck() )->run() ) {
					$status = __( "This server doesn't support passkeys.", 'wp-simple-firewall' );
				}
				break;
			default:
				break;
		}
		return $status;
	}
}

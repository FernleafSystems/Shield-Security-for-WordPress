<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Which of this site's administrators have two-factor authentication on their account.
 *
 * It's asked on each of the plugin's own pages and on the WordPress dashboard, so it looks people up only when
 * nothing cheaper settles the answer, and then all of them together. Each shortcut starts from the methods login
 * offers, so it can't count a method that login doesn't ask for.
 */
class AdminTwoFactorCoverage {

	use PluginControllerConsumer;

	/**
	 * @return list<\WP_User>
	 */
	public function administrators() :array {
		return \function_exists( 'get_users' ) ? \array_values( get_users( [
			'role'    => 'administrator',
			'orderby' => 'display_name',
		] ) ) : [];
	}

	/**
	 * @param list<\WP_User> $users
	 * @return list<\WP_User> those with no two-factor method active on their account
	 */
	public function unprotectedAmong( array $users ) :array {
		// Email required of a person's role covers them whatever else is true of them, while login offers it.
		if ( !empty( $users ) && Provider\Email::ProviderEnabled()
			 && self::con()->comps->mfa->isProviderOffered( Provider\Email::class ) ) {
			$users = \array_values( \array_filter(
				$users,
				static fn( \WP_User $user ) :bool => !( new Provider\Email( $user ) )->isEnforced()
			) );
		}

		// With no method that a person sets up for themselves, there's nothing more to find.
		if ( empty( $users ) || !$this->canAnyMethodBeSetUp() ) {
			return $users;
		}

		$con = self::con();
		$con->user_metas->preload( $users );
		( new Utilties\MfaRecordsHandler() )->preload( $users );
		return \array_values( \array_filter(
			$users,
			static fn( \WP_User $user ) :bool => empty( $con->comps->mfa->getProvidersActiveForUser( $user ) )
		) );
	}

	/**
	 * Whether this site has any method enabled that a person sets up on their own account. Backup codes don't
	 * count: they only work beside another method. A method from another plugin always counts, as only it knows
	 * who can use it.
	 */
	private function canAnyMethodBeSetUp() :bool {
		$can = false;
		foreach ( self::con()->comps->mfa->collateMfaProviderClasses() as $provider ) {
			if ( !\is_subclass_of( $provider, Provider\AbstractShieldProvider::class ) ) {
				$can = true;
			}
			elseif ( $provider === Provider\Email::class ) {
				$can = Provider\Email::ProviderEnabled() && Provider\Email::AnyUserCanTurnOn();
			}
			elseif ( $provider !== Provider\BackupCodes::class ) {
				$can = $provider::ProviderEnabled();
			}
			if ( $can ) {
				break;
			}
		}
		return $can;
	}
}

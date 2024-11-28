<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Users extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire Users Zone';
	}

	public function components() :array {
		return [
			Component\PwnedPasswords::class,
			Component\PasswordStrength::class,
			Component\InactiveUsers::class,
			Component\SpamUserRegisterBlock::class,
		];
	}

	public function description() :array {
		return [
			\implode( ' ', [
				__( 'These security options tie-in with your Login security measures.', 'wp-simple-firewall' ),
				__( "Here, however, we're focusing on the user accounts themselves to ensure that they're in a secure state.", 'wp-simple-firewall' ),
			] ),
			\implode( ' ', [
				__( "It's critical that users don't re-use passwords, particularly if they've been exposed through a data breach on another site or service.", 'wp-simple-firewall' ),
				__( "This is where 'pwned' password scanning helps, by identifying the use of leaked passwords and prompting those users to update, or stopping the saving of those passwords.", 'wp-simple-firewall' ),
				__( "Enforcing a minimum password strength, too, will protect user accounts from brute-force login attacks.", 'wp-simple-firewall' ),
			] ),
			__( "Automatically suspending user accounts that have been unused for a good period of time will prevent those accounts from being mis-used at a later date.", 'wp-simple-firewall' ),
			__( "Lastly, we want to prevent the ability of bots (or humans) to register SPAM accounts on the site, for any reason.", 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'person-badge-fill';
	}

	public function title() :string {
		return __( 'Users', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for user accounts.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleUsers::class;
	}
}
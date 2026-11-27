<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class DefaultAdminUser extends Base {

	public const SLUG = 'default_admin_user';
	public const WEIGHT = 1;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	protected function testIfProtected() :bool {
		$adminUser = Services::WpUsers()->getUserByUsername( 'admin' );
		return !$adminUser instanceof \WP_User || !user_can( $adminUser, 'manage_options' );
	}

	protected function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl( 'users.php' ), [
			's' => 'admin',
		] );
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function text() :array {
		return \array_merge(
			parent::text(),
			[
				'fix' => __( 'Manage Users', 'wp-simple-firewall' ),
			]
		);
	}

	public function title() :string {
		return __( 'Default Admin User', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( "The default 'admin' user is no longer available.", 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "The default 'admin' user is still available.", 'wp-simple-firewall' );
	}

	public function guidance() :array {
		return [
			'checks'   => __( "Shield looks for a user account with the username 'admin' that has administrator access.", 'wp-simple-firewall' ),
			'why'      => [
				__( "'admin' is the username attackers try first when guessing passwords, because so many sites have it.", 'wp-simple-firewall' ),
				__( "That only matters if the password can be guessed. With a strong, unique password on the account, and above all with Shield's two-factor authentication set up for it, keeping the 'admin' username is fine.", 'wp-simple-firewall' ),
				__( "Shield's Security Admin adds another layer. It puts Shield's settings behind a PIN, and can stop one administrator from creating or changing another, so that a break-in to a single account does less damage.", 'wp-simple-firewall' ),
			],
			'steps'    => [
				__( "You don't have to remove this account. Give it a long, unique password and set up Shield's two-factor authentication for it. With both in place it's fine to keep, and you can ignore this item in the queue.", 'wp-simple-firewall' ),
				__( "Or, to retire the username: WordPress doesn't let you change one, so add a new user with the Administrator role and an email address the 'admin' account isn't using. Log in as that user, then delete the 'admin' account. When WordPress asks what to do with its content, attribute it to your new account, so that no posts or pages are lost.", 'wp-simple-firewall' ),
				__( "Or change the 'admin' account's role to anything other than Administrator. That also clears this item.", 'wp-simple-firewall' ),
			],
			'who'      => __( 'Any administrator of the site can do this.', 'wp-simple-firewall' ),
			'practice' => [
				__( 'Give each person their own account, not a shared one.', 'wp-simple-firewall' ),
				__( 'Use a long, unique password and two-factor authentication on every administrator account.', 'wp-simple-firewall' ),
				__( 'Keep the number of administrators as small as you can.', 'wp-simple-firewall' ),
			],
		];
	}
}

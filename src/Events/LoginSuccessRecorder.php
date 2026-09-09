<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LoginSuccessRecorder {

	use ExecOnce;
	use PluginControllerConsumer;

	/** @var array{user_id:int,token:string}|null */
	private ?array $candidate = null;

	/** @var array<int,array<string,bool>> */
	private array $recorded = [];

	protected function run() {
		add_action( 'set_logged_in_cookie', [ $this, 'onSetLoggedInCookie' ], 5, 6 );
		add_action( 'clear_auth_cookie', [ $this, 'onClearAuthCookie' ], 0 );
		// Shield's MFA challenge runs at 15 and invalidates the initial login token.
		add_action( 'wp_login', [ $this, 'onWpLogin' ], 20, 2 );
	}

	public function onSetLoggedInCookie( $cookie, $expire, $expiration, $userID, $scheme, $token ) :void {
		$this->candidate = \is_int( $userID ) && $userID > 0 && \is_string( $token ) && $token !== ''
			? [ 'user_id' => $userID, 'token' => $token ] : null;
	}

	public function onClearAuthCookie() :void {
		$this->candidate = null;
	}

	public function onWpLogin( $username, $user ) :void {
		if ( $user instanceof \WP_User ) {
			$this->recordSuccessfulLogin( $user );
		}
	}

	/** Called only after wp_login interception or successful Shield MFA completion. */
	public function recordSuccessfulLogin( \WP_User $user ) :void {
		if ( !$this->isAlreadyExecuted()
			 || !self::con()->comps->opts_lookup->isPluginEnabled()
			 || self::con()->this_req->is_force_off
			 || $this->candidate === null
			 || $user->ID !== $this->candidate[ 'user_id' ] ) {
			return;
		}

		$token = $this->candidate[ 'token' ];
		if ( isset( $this->recorded[ $user->ID ][ $token ] )
			 || !\WP_Session_Tokens::get_instance( $user->ID )->verify( $token ) ) {
			return;
		}

		// Mark before dispatch so event listeners cannot reenter and count it again.
		$this->recorded[ $user->ID ][ $token ] = true;
		self::con()->comps->events->fireEvent( 'login_success' );
	}
}

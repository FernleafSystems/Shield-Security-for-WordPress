<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend\UserSuspendController;
use FernleafSystems\Wordpress\Plugin\Shield\Users\BulkUpdateUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * This module is set to "run if whitelisted", so we must ensure any
	 * actions taken by this module respect whether the current visitor is whitelisted.
	 */
	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', [ $this, 'addUserStatusLastLogin' ] );
		add_filter( 'wpmu_users_columns', [ $this, 'addUserStatusLastLogin' ] );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( $this->getCon()->this_req->wp_is_xmlrpc && $mod->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		// This controller handles visitor whitelisted status internally.
		( new UserSuspendController() )
			->setMod( $mod )
			->execute();

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $userID ) {
			$this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $userID ) );
		} );

		if ( !$this->getCon()->this_req->request_bypasses_all_restrictions ) {
			( new Lib\Session\UserSessionHandler() )
				->setMod( $this->getMod() )
				->execute();
			( new Lib\Password\UserPasswordHandler() )
				->setMod( $this->getMod() )
				->execute();
			( new Lib\Registration\EmailValidate() )
				->setMod( $this->getMod() )
				->execute();
		}
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		$con = $this->getCon();
		if ( $con->isValidAdminArea() ) {

			$thisGroup = [
				'title' => __( 'Recent Users', 'wp-simple-firewall' ),
				'href'  => $con->plugin_urls ? $con->plugin_urls->adminTopNav( $con->plugin_urls::NAV_USER_SESSIONS ) :
					$con->getModule_Insights()->getUrl_Sessions(),
				'items' => [],
			];

			$recent = ( new FindSessions() )->mostRecent();
			if ( !empty( $recent ) ) {

				foreach ( $recent as $userID => $user ) {
					$thisGroup[ 'items' ][] = [
						'id'    => $con->prefix( 'meta-'.$userID ),
						'title' => sprintf( '<a href="%s">%s (%s)</a>',
							Services::WpUsers()->getAdminUrl_ProfileEdit( $userID ),
							$user[ 'user_login' ],
							$user[ 'ip' ]
						),
					];
				}
			}

			if ( !empty( $thisGroup[ 'items' ] ) ) {
				$groups[] = $thisGroup;
			}
		}
		return $groups;
	}

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array $cols
	 * @return array
	 */
	public function addUserStatusLastLogin( $cols ) {

		if ( is_array( $cols ) ) {
			$customColName = $this->getCon()->prefix( 'col_user_status' );
			if ( !isset( $cols[ $customColName ] ) ) {
				$cols[ $customColName ] = __( 'User Status', 'wp-simple-firewall' );
			}

			add_filter( 'manage_users_custom_column', function ( $content, $colName, $userID ) use ( $customColName ) {

				if ( $colName === $customColName ) {
					$user = Services::WpUsers()->getUserById( $userID );
					if ( $user instanceof \WP_User ) {

						$lastLoginAt = $this->getCon()->getUserMeta( $user )->record->last_login_at;
						if ( $lastLoginAt > 0 ) {
							$lastLogin = Services::Request()
												 ->carbon()
												 ->setTimestamp( $lastLoginAt )
												 ->diffForHumans();
						}
						else {
							$lastLogin = __( 'Not Recorded', 'wp-simple-firewall' );
						}

						$additionalContent = apply_filters( 'shield/user_status_column', [
							$content,
							sprintf( '<em>%s</em>: %s', __( 'Last Login', 'wp-simple-firewall' ), $lastLogin )
						], $user );

						$content = implode( '<br/>', array_filter( array_map( 'trim', $additionalContent ) ) );
					}
				}

				return $content;
			}, 10, 3 );
		}

		return $cols;
	}

	public function runHourlyCron() {
		( new BulkUpdateUserMeta() )
			->setCon( $this->getCon() )
			->execute();
	}
}
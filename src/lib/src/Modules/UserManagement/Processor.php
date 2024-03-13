<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops as IPDB;
use FernleafSystems\Wordpress\Plugin\Shield\Users\BulkUpdateUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	/**
	 * This module is set to "run if whitelisted", so we must ensure any
	 * actions taken by this module respect whether the current visitor is whitelisted.
	 */
	protected function run() {
		$con = self::con();
		// Adds last login indicator column
		add_filter( 'manage_users_columns', [ $this, 'addUserStatusLastLogin' ] );
		add_filter( 'wpmu_users_columns', [ $this, 'addUserStatusLastLogin' ] );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( $con->this_req->wp_is_xmlrpc && $con->getModule_Plugin()->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		// This controller handles visitor whitelisted status internally.
		self::con()->comps->user_suspend->execute();

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $userID ) {
			self::con()->user_metas->for( Services::WpUsers()->getUserById( $userID ) );
		} );

		if ( !$con->this_req->request_bypasses_all_restrictions ) {
			( new Lib\Session\UserSessionHandler() )->execute();
			( new Lib\Password\UserPasswordHandler() )->execute();
			( new Lib\Registration\EmailValidate() )->execute();
		}
	}

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array $cols
	 * @return array
	 */
	public function addUserStatusLastLogin( $cols ) {

		if ( \is_array( $cols ) ) {
			$customColName = self::con()->prefix( 'col_user_status' );
			if ( !isset( $cols[ $customColName ] ) ) {
				$cols[ $customColName ] = __( 'User Status', 'wp-simple-firewall' );
			}

			add_filter( 'manage_users_custom_column', function ( $content, $colName, $userID ) use ( $customColName ) {

				if ( $colName === $customColName ) {
					$user = Services::WpUsers()->getUserById( $userID );
					if ( $user instanceof \WP_User ) {
						$con = self::con();

						$meta = $con->user_metas->for( $user );
						$lastLoginAt = (int)$meta->record->last_login_at;
						$carbon = Services::Request()
										  ->carbon()
										  ->setTimestamp( $lastLoginAt );

						/** @var IPDB\Record $ipRecord */
						$ipRecord = $con->db_con->dbhIPs()->getQuerySelector()->byId( $meta->record->ip_ref );

						$additionalContent = apply_filters( 'shield/user_status_column', [
							$content,
							sprintf( '<em title="%s">%s</em>: %s',
								$lastLoginAt > 0 ? $carbon->toIso8601String() : __( 'Not Recorded', 'wp-simple-firewall' ),
								__( 'Last Login', 'wp-simple-firewall' ),
								$lastLoginAt > 0 ? $carbon->diffForHumans() : __( 'Not Recorded', 'wp-simple-firewall' )
							),
							sprintf( '<em title="%s">%s</em>: %s',
								empty( $ipRecord->ip ) ? __( 'Unknown', 'wp-simple-firewall' ) : $ipRecord->ip,
								__( 'Last Known IP', 'wp-simple-firewall' ),
								empty( $ipRecord->ip ) ? __( 'Unknown', 'wp-simple-firewall' ) :
									sprintf( '<a href="%s" target="_blank">%s</a>',
										$con->plugin_urls->ipAnalysis( $ipRecord->ip ),
										$ipRecord->ip
									)
							),
						], $user );

						$content = \implode( '<br/>', \array_filter( \array_map( '\trim', $additionalContent ) ) );
					}
				}

				return $content;
			}, 10, 3 );
		}

		return $cols;
	}

	public function runHourlyCron() {
		( new BulkUpdateUserMeta() )->execute();
	}

	/**
	 * @deprecated 19.1
	 */
	public function addAdminBarMenuGroup( array $groups ) :array {
		return $groups;
	}
}
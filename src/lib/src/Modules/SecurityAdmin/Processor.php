<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	protected function run() {
		add_filter( $this->getCon()->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ] );

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isEnabledWhitelabel() ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$mod->getWhiteLabelController()->execute();
		}
	}

	/**
	 * @param bool $bHasPermission
	 * @return bool
	 */
	public function adjustUserAdminPermissions( $bHasPermission = true ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $bHasPermission &&
			   ( $mod->isRegisteredSecAdminUser() || $mod->isSecAdminSessionValid()
				 || $mod->testSecAccessKeyRequest() );
	}

	public function onWpInit() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			/** @var Options $opts */
			$opts = $this->getOptions();

			if ( !$mod->isUpgrading() && !Services::WpGeneral()->isLoginRequest() ) {
				add_filter( 'pre_update_option', [ $this, 'blockOptionsSaves' ], 1, 3 );
			}

			if ( $opts->isSecAdminRestrictUsersEnabled() ) {
				add_filter( 'editable_roles', [ $this, 'restrictEditableRoles' ], 100, 1 );
				add_filter( 'user_has_cap', [ $this, 'restrictAdminUserChanges' ], 100, 3 );
				add_action( 'delete_user', [ $this, 'restrictAdminUserDelete' ], 100, 1 );
				add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
				add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
				add_action( 'set_user_role', [ $this, 'restrictSetUserRole' ], 100, 3 );
			}

			$aPluginRestrictions = $opts->getAdminAccessArea_Plugins();
			if ( !empty( $aPluginRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disablePluginManipulation' ], 0, 3 );
			}

			$aThemeRestrictions = $opts->getAdminAccessArea_Themes();
			if ( !empty( $aThemeRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disableThemeManipulation' ], 0, 3 );
			}

			$aPostRestrictions = $opts->getAdminAccessArea_Posts();
			if ( !empty( $aPostRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disablePostsManipulation' ], 0, 3 );
			}

			if ( !$this->getCon()->isThisPluginModuleRequest() ) {
				add_action( 'admin_footer', [ $this, 'printAdminAccessAjaxForm' ] );
			}
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 */
	public function restrictAddUserRole( $nUserId, $sRole ) {
		$oWpUsers = Services::WpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
			$oModUser->remove_role( 'administrator' );
			add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @param array  $aOldRoles
	 */
	public function restrictSetUserRole( $nUserId, $sRole, $aOldRoles = [] ) {
		$oWpUsers = Services::WpUsers();

		$sRole = strtolower( $sRole );
		if ( !is_array( $aOldRoles ) ) {
			$aOldRoles = [];
		}

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId ) {
			$bNewRoleIsAdmin = $sRole == 'administrator';

			// 1. Setting administrator role where it doesn't previously exist
			if ( $bNewRoleIsAdmin && !in_array( 'administrator', $aOldRoles ) ) {
				$bRevert = true;
			}
			// 2. Setting non-administrator role when previous roles included administrator
			elseif ( !$bNewRoleIsAdmin && in_array( 'administrator', $aOldRoles ) ) {
				$bRevert = true;
			}
			else {
				$bRevert = false;
			}

			if ( $bRevert ) {
				$oModUser = $oWpUsers->getUserById( $nUserId );
				remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
				remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
				$oModUser->remove_role( $sRole );
				foreach ( $aOldRoles as $sPreExistingRoles ) {
					$oModUser->add_role( $sPreExistingRoles );
				}
				add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
				add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
			}
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 */
	public function restrictRemoveUserRole( $nUserId, $sRole ) {
		$oWpUsers = Services::WpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
			$oModUser->add_role( 'administrator' );
			add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int $nId
	 */
	public function restrictAdminUserDelete( $nId ) {
		$WPU = Services::WpUsers();
		$oUserToDelete = $WPU->getUserById( $nId );
		if ( $oUserToDelete && $WPU->isUserAdmin( $oUserToDelete ) ) {
			Services::WpGeneral()
					->wpDie( __( 'Sorry, deleting administrators is currently restricted to your Security Admin', 'wp-simple-firewall' ) );
		}
	}

	/**
	 * @param array[] $roles
	 * @return array[]
	 */
	public function restrictEditableRoles( $roles ) {
		if ( isset( $roles[ 'administrator' ] ) ) {
			unset( $roles[ 'administrator' ] );
		}
		return $roles;
	}

	/**
	 * This hooked function captures the attempts to modify the user role using the standard
	 * WordPress profile edit pages. It doesn't sufficiently capture the AJAX request to
	 * modify user roles. (see user role hooks)
	 * @param array $allCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 */
	public function restrictAdminUserChanges( $allCaps, $cap, $args ) {
		/** @var string $userCap */
		$userCap = $args[ 0 ];

		$aRelevantCaps = [ 'edit_users', 'create_users' ];

		// If we're registered with Admin Access we don't modify anything
		if ( in_array( $userCap, $aRelevantCaps ) ) {
			$bBlockCapability = false;

			$req = Services::Request();
			$oWpUsers = Services::WpUsers();

			$oPostUser = false;
			$sPostUserlogin = $req->post( 'user_login' );
			if ( empty( $sPostUserlogin ) ) {
				$nPostUserId = $req->post( 'user_id' );
				if ( !empty( $nPostUserId ) ) {
					$oPostUser = $oWpUsers->getUserById( $nPostUserId );
				}
			}
			else {
				$oPostUser = $oWpUsers->getUserByUsername( $sPostUserlogin );
			}

			$sRequestRole = strtolower( $req->post( 'role', '' ) );

			if ( $oPostUser instanceof \WP_User ) {
				// editing an existing user other than yourself?
				if ( $oPostUser->user_login != $oWpUsers->getCurrentWpUsername() ) {

					if ( $oWpUsers->isUserAdmin( $oPostUser ) || ( $sRequestRole == 'administrator' ) ) {
						$bBlockCapability = true;
					}
				}
			}
			elseif ( $sRequestRole == 'administrator' ) { //creating a new admin user?
				$bBlockCapability = true;
			}

			if ( $bBlockCapability ) {
				$allCaps[ $userCap ] = false;
			}
		}

		return $allCaps;
	}

	protected function getUserPagesToRestrict() :array {
		return [
			/* 'user-new.php', */
			'user-edit.php',
			'users.php',
		];
	}

	/**
	 * Need to always re-test isPluginAdmin() because there's a dynamic filter in there to
	 * permit saving by the plugin itself.
	 *
	 * Right before a plugin option is due to update it will check that we have permissions to do so
	 * and if not, will * revert the option to save to the previous one.
	 * @param mixed  $mNewOptionValue
	 * @param string $key
	 * @param mixed  $mOldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $mNewOptionValue, $key, $mOldValue ) {

		if ( !$this->getCon()->isPluginAdmin() && is_string( $key )
			 && ( $this->isOptionForThisPlugin( $key ) || $this->isOptionRestricted( $key ) ) ) {
			$mNewOptionValue = $mOldValue;
		}

		return $mNewOptionValue;
	}

	private function isOptionForThisPlugin( string $key ) :bool {
		return preg_match( $this->getOptionRegexPattern(), $key ) > 0;
	}

	private function isOptionRestricted( string $key ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getAdminAccessArea_Options()
			   && in_array( $key, $opts->getOptionsToRestrict() );
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disablePluginManipulation( $aAllCaps, $cap, $aArgs ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];

		// special case for plugin info thickbox for changelog
		$bIsChangelog = defined( 'IFRAME_REQUEST' )
						&& ( $sRequestedCapability === 'install_plugins' )
						&& ( $req->query( 'section' ) == 'changelog' )
						&& $req->query( 'plugin' );
		if ( $bIsChangelog ) {
			return $aAllCaps;
		}

		$aEditCapabilities = [ 'activate_plugins', 'delete_plugins', 'install_plugins', 'update_plugins' ];

		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$aAreaRestrictions = $opts->getAdminAccessArea_Plugins();
			if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disableThemeManipulation( $aAllCaps, $cap, $aArgs ) {
		// If we're registered with Admin Access we don't modify anything
		if ( $this->getCon()->isPluginAdmin() ) {
			return $aAllCaps;
		}

		/** @var Options $opts */
		$opts = $this->getOptions();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];
		$aEditCapabilities = [
			'switch_themes',
			'edit_theme_options',
			'install_themes',
			'update_themes',
			'delete_themes'
		];

		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$aAreaRestrictions = $opts->getAdminAccessArea_Themes();
			if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 */
	public function disablePostsManipulation( $aAllCaps, $cap, $args ) {
		if ( $this->getCon()->isPluginAdmin() ) {
			return $aAllCaps;
		}

		/** @var Options $opts */
		$opts = $this->getOptions();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $args[ 0 ];
		$aEditCapabilities = [
			'edit_post',
			'publish_post',
			'delete_post',
			'edit_posts',
			'publish_posts',
			'delete_posts',
			'edit_page',
			'publish_page',
			'delete_page',
			'edit_pages',
			'publish_pages',
			'delete_pages'
		];
		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$sRequestedCapabilityTrimmed = str_replace( [
				'_posts',
				'_pages',
				'_post',
				'_page'
			], '', $sRequestedCapability ); //Order of items in this array is important!
			$aAreaRestrictions = $opts->getAdminAccessArea_Posts();
			if ( in_array( $sRequestedCapabilityTrimmed, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}
		return $aAllCaps;
	}

	private function getOptionRegexPattern() :string {
		return sprintf( '/^%s.*_options$/', $this->getCon()->getOptionStoragePrefix() );
	}

	public function printAdminAccessAjaxForm() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aRenderData = [
			'flags'       => [
				'restrict_options' => $opts->getAdminAccessArea_Options()
			],
			'strings'     => [
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'unlock_link'        => $this->getUnlockLinkHtml(),
			],
			'js_snippets' => [
				'options_to_restrict' => "'".implode( "','", $opts->getOptionsToRestrict() )."'",
			],
			'ajax'        => [
				'sec_admin_login_box' => $mod->getAjaxActionData( 'sec_admin_login_box', true )
			]
		];
		add_thickbox();
		echo $mod->renderTemplate( 'snippets/admin_access_login_box.php', $aRenderData );
	}

	/**
	 * @param string $sLinkText
	 * @return string
	 */
	protected function getUnlockLinkHtml( $sLinkText = '' ) {
		if ( empty( $sLinkText ) ) {
			$sLinkText = __( 'Unlock', 'wp-simple-firewall' );
		}
		return sprintf(
			'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
			'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
			__( 'Security Admin Login', 'wp-simple-firewall' ),
			$sLinkText
		);
	}
}
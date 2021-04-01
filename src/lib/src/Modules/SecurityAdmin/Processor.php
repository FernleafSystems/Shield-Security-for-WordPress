<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isEnabledWhitelabel() ) {
			$mod->getWhiteLabelController()->execute();
		}

		$mod->getSecurityAdminController()->execute();
	}

	/**
	 * @param bool $bHasPermission
	 * @return bool
	 * @deprecated 11.1
	 */
	public function adjustUserAdminPermissions( $bHasPermission = true ) :bool {
		return $bHasPermission;
	}

	public function onWpInit() {
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @deprecated 11.1
	 */
	public function restrictAddUserRole( $nUserId, $sRole ) {
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @param array  $aOldRoles
	 * @deprecated 11.1
	 */
	public function restrictSetUserRole( $nUserId, $sRole, $aOldRoles = [] ) {
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @deprecated 11.1
	 */
	public function restrictRemoveUserRole( $nUserId, $sRole ) {
	}

	/**
	 * @param int $nId
	 * @deprecated 11.1
	 */
	public function restrictAdminUserDelete( $nId ) {
	}

	/**
	 * @param array[] $roles
	 * @return array[]
	 * @deprecated 11.1
	 */
	public function restrictEditableRoles( $roles ) {
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
	 * @deprecated 11.1
	 */
	public function restrictAdminUserChanges( $allCaps, $cap, $args ) {
		return $allCaps;
	}

	/**
	 * @deprecated 11.1
	 */
	public function blockOptionsSaves( $mNewOptionValue, $key, $mOldValue ) {
		return $mNewOptionValue;
	}

	/**
	 * @deprecated 11.1
	 */
	private function isOptionForThisPlugin( string $key ) :bool {
		return preg_match( $this->getOptionRegexPattern(), $key ) > 0;
	}

	/**
	 * @deprecated 11.1
	 */
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
	 * @deprecated 11.1
	 */
	public function disablePluginManipulation( $aAllCaps, $cap, $aArgs ) {
		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 * @deprecated 11.1
	 */
	public function disableThemeManipulation( $aAllCaps, $cap, $aArgs ) {
		// If we're registered with Admin Access we don't modify anything
		if ( $this->getCon()->isPluginAdmin() ) {
			return $aAllCaps;
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 * @deprecated 11.1
	 */
	public function disablePostsManipulation( $aAllCaps, $cap, $args ) {
		return $aAllCaps;
	}

	/**
	 * @deprecated 11.1
	 */
	private function getOptionRegexPattern() :string {
		return sprintf( '/^%s.*_options$/', $this->getCon()->getOptionStoragePrefix() );
	}

	/**
	 * @deprecated 11.1
	 */
	public function printAdminAccessAjaxForm() {
	}

	/**
	 * @param string $sLinkText
	 * @return string
	 * @deprecated 11.1
	 */
	protected function getUnlockLinkHtml( $sLinkText = '' ) {
		return '';
	}
}
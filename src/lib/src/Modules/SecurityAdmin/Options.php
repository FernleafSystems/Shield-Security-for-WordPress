<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Options {

	public function preSave() :void {
		// Restricting Activate Plugins also means restricting the rest.
		$p = $this->getOpt( 'admin_access_restrict_plugins', [] );
		if ( $this->isOptChanged( 'admin_access_restrict_plugins' ) && \in_array( 'activate_plugins', $p ) ) {
			$this->setOpt( 'admin_access_restrict_plugins',
				\array_unique( \array_merge( $p, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$t = $this->getOpt( 'admin_access_restrict_themes', [] );
		if ( $this->isOptChanged( 'admin_access_restrict_themes' )
			 && \in_array( 'switch_themes', $t ) && \in_array( 'edit_theme_options', $t ) ) {
			$this->setOpt( 'admin_access_restrict_themes',
				\array_unique( \array_merge( $t, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$posts = $this->getOpt( 'admin_access_restrict_posts', [] );
		if ( $this->isOptChanged( 'admin_access_restrict_posts' ) && \in_array( 'edit', $posts ) ) {
			$this->setOpt( 'admin_access_restrict_posts',
				\array_unique( \array_merge( $posts, [ 'publish', 'delete' ] ) )
			);
		}
	}

	private function getRestrictedOptions() :array {
		$options = $this->getDef( 'options_to_restrict' );
		return \is_array( $options ) ? $options : [];
	}

	public function getOptionsToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_options';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	public function getOptionsPagesToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_pages';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	public function getSecurityAdminUsers() :array {
		$users = $this->getOpt( 'sec_admin_users', [] );
		return ( \is_array( $users ) && self::con()->isPremiumActive() ) ? $users : [];
	}

	public function getSecurityPIN() :string {
		return (string)$this->getOpt( 'admin_access_key', '' );
	}

	public function hasSecurityPIN() :bool {
		return !empty( $this->getSecurityPIN() );
	}

	public function isEmailOverridePermitted() :bool {
		return $this->isOpt( 'allow_email_override', 'Y' );
	}

	public function isSecAdminRestrictUsersEnabled() :bool {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	public function isRestrictWpOptions() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}
}
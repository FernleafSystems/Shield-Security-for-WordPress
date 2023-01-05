<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const HASH_DELETE = '32f68a60cef40faedbc6af20298c1a1e';

	/**
	 * @var Lib\WhiteLabel\WhitelabelController
	 */
	private $whitelabelCon;

	/**
	 * @var Lib\SecurityAdmin\SecurityAdminController
	 */
	private $securityAdminCon;

	protected function enumRuleBuilders() :array {
		return [
			Rules\Build\IsSecurityAdmin::class,
		];
	}

	public function getWhiteLabelController() :Lib\WhiteLabel\WhitelabelController {
		if ( !$this->whitelabelCon instanceof Lib\WhiteLabel\WhitelabelController ) {
			$this->whitelabelCon = ( new Lib\WhiteLabel\WhitelabelController() )->setMod( $this );
		}
		return $this->whitelabelCon;
	}

	public function getSecurityAdminController() :Lib\SecurityAdmin\SecurityAdminController {
		if ( !$this->securityAdminCon instanceof Lib\SecurityAdmin\SecurityAdminController ) {
			$this->securityAdminCon = ( new Lib\SecurityAdmin\SecurityAdminController() )->setMod( $this );
		}
		return $this->securityAdminCon;
	}

	public function runDailyCron() {
		parent::runDailyCron();
		$this->runMuHandler();
	}

	private function runMuHandler() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$mu = $this->getCon()->mu_handler;
		try {
			$opts->isEnabledMU() ? $mu->convertToMU() : $mu->convertToStandard();
		}
		catch ( \Exception $e ) {
		}
		$opts->setOpt( 'enable_mu', $mu->isActiveMU() ? 'Y' : 'N' );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getSecAdminLoginAjaxData() :array {
		return ActionData::Build( Actions\SecurityAdminLogin::SLUG );
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		// Verify whitelabel images
		$this->getWhiteLabelController()->verifyUrls();

		$opts->setOpt( 'sec_admin_users',
			( new Lib\SecurityAdmin\VerifySecurityAdminList() )
				->setMod( $this )
				->run( $opts->getSecurityAdminUsers() )
		);

		if ( hash_equals( $opts->getSecurityPIN(), self::HASH_DELETE ) ) {
			$opts->clearSecurityAdminKey();
			( new Lib\SecurityAdmin\Ops\ToggleSecAdminStatus() )
				->setMod( $this )
				->turnOff();
			// If you delete the PIN, you also delete the sec admins. Prevents a lock out bug.
			$opts->setOpt( 'sec_admin_users', [] );
		}

		$this->runMuHandler();
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		// Restricting Activate Plugins also means restricting the rest.
		$plugins = $opts->getOpt( 'admin_access_restrict_plugins', [] );
		if ( in_array( 'activate_plugins', is_array( $plugins ) ? $plugins : [] ) ) {
			$opts->setOpt( 'admin_access_restrict_plugins',
				array_unique( array_merge( $plugins, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$themes = $opts->getOpt( 'admin_access_restrict_themes', [] );
		if ( is_array( $themes ) && in_array( 'switch_themes', $themes ) && in_array( 'edit_theme_options', $themes ) ) {
			$opts->setOpt( 'admin_access_restrict_themes',
				array_unique( array_merge( $themes, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$posts = $opts->getOpt( 'admin_access_restrict_posts', [] );
		if ( !is_array( $posts ) ) {
			$posts = [];
		}
		if ( in_array( 'edit', $posts ) ) {
			$posts = array_unique( array_merge( $posts, [ 'publish', 'delete' ] ) );
			$opts->setOpt( 'admin_access_restrict_posts', $posts );
		}
	}

	/**
	 * @deprecated 17.0
	 */
	public function preDeactivatePlugin() {
	}
}
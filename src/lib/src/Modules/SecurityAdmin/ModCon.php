<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'admin_access_restriction';

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
		return $this->whitelabelCon ?? $this->whitelabelCon = ( new Lib\WhiteLabel\WhitelabelController() )->setMod( $this );
	}

	public function getSecurityAdminController() :Lib\SecurityAdmin\SecurityAdminController {
		return $this->securityAdminCon ?? $this->securityAdminCon = ( new Lib\SecurityAdmin\SecurityAdminController() )->setMod( $this );
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
}
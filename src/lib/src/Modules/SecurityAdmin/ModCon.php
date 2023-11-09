<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
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
		return $this->whitelabelCon ?? $this->whitelabelCon = new Lib\WhiteLabel\WhitelabelController();
	}

	public function getSecurityAdminController() :Lib\SecurityAdmin\SecurityAdminController {
		return $this->securityAdminCon ?? $this->securityAdminCon = new Lib\SecurityAdmin\SecurityAdminController();
	}

	public function runDailyCron() {
		parent::runDailyCron();
		$this->runMuHandler();
	}

	private function runMuHandler() {
		/** @var Options $opts */
		$opts = $this->opts();

		$mu = self::con()->mu_handler;
		try {
			$opts->isOpt( 'enable_mu', 'Y' ) ? $mu->convertToMU() : $mu->convertToStandard();
		}
		catch ( \Exception $e ) {
		}
		$opts->setOpt( 'enable_mu', $mu->isActiveMU() ? 'Y' : 'N' );
	}

	public function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();

		// Verify whitelabel images
		$this->getWhiteLabelController()->verifyUrls();

		$opts->setOpt( 'sec_admin_users',
			( new Lib\SecurityAdmin\VerifySecurityAdminList() )->run( $opts->getSecurityAdminUsers() )
		);

		$this->runMuHandler();
	}

	public function doPrePluginOptionsSave() {
	}
}
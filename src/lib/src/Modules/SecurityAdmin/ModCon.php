<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'admin_access_restriction';

	/**
	 * @var Lib\WhiteLabel\WhitelabelController
	 */
	private $whitelabelCon;

	/**
	 * @var Lib\SecurityAdmin\SecurityAdminController
	 */
	private $securityAdminCon;

	public function getWhiteLabelController() :Lib\WhiteLabel\WhitelabelController {
		return self::con()->comps !== null ? self::con()->comps->whitelabel :
			( $this->whitelabelCon ?? $this->whitelabelCon = new Lib\WhiteLabel\WhitelabelController() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSecurityAdminController() :Lib\SecurityAdmin\SecurityAdminController {
		return self::con()->comps !== null ? self::con()->comps->sec_admin :
			( $this->securityAdminCon ?? $this->securityAdminCon = new Lib\SecurityAdmin\SecurityAdminController() );
	}

	public function runDailyCron() {
		self::con()->comps->mu->run();
	}

	/**
	 * @deprecated 19.1
	 */
	public function runMuHandler() {
		$mu = self::con()->mu_handler;
		try {
			$this->opts()->isOpt( 'enable_mu', 'Y' ) ? $mu->convertToMU() : $mu->convertToStandard();
		}
		catch ( \Exception $e ) {
		}
		$this->opts()->setOpt( 'enable_mu', $mu->isActiveMU() ? 'Y' : 'N' );
	}
}
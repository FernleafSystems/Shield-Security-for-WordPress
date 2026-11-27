<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\AdminTwoFactorCoverage;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * A to-do while any administrator has no two-factor authentication on their account.
 */
class AdminTwoFactor extends Base {

	public const SLUG = 'admin_two_factor';

	/**
	 * @var array{total:int, unprotected:int}|null
	 */
	private ?array $counts = null;

	public function channel() :string {
		return self::CHANNEL_ACTION;
	}

	/**
	 * A site can give its administrators a role of its own, and then there's nobody here to count.
	 */
	protected function isApplicable() :bool {
		return $this->counts()[ 'total' ] > 0;
	}

	protected function testIfProtected() :bool {
		return $this->counts()[ 'unprotected' ] === 0;
	}

	protected function hrefFull() :string {
		return URL::Build( Services::WpGeneral()->getAdminUrl( 'users.php' ), [
			'role' => 'administrator',
		] );
	}

	protected function hrefFullTargetBlank() :bool {
		return true;
	}

	protected function text() :array {
		return \array_merge(
			parent::text(),
			[
				'fix' => __( 'Manage Users', 'wp-simple-firewall' ),
			]
		);
	}

	public function title() :string {
		return __( 'Administrator Two-Factor Authentication', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		$total = $this->counts()[ 'total' ];
		return sprintf(
			/* translators: %s: number of administrators */
			_n(
				'The administrator has two-factor authentication.',
				'All %s administrators have two-factor authentication.',
				$total,
				'wp-simple-firewall'
			),
			$total
		);
	}

	public function descUnprotected() :string {
		$counts = $this->counts();
		return sprintf(
			/* translators: %1$s: number of administrators without two-factor authentication, %2$s: number of administrators */
			_n(
				'%1$s of %2$s administrators has no two-factor authentication.',
				'%1$s of %2$s administrators have no two-factor authentication.',
				$counts[ 'unprotected' ],
				'wp-simple-firewall'
			),
			$counts[ 'unprotected' ],
			$counts[ 'total' ]
		);
	}

	/**
	 * @return array{total:int, unprotected:int}
	 */
	private function counts() :array {
		if ( $this->counts === null ) {
			$coverage = new AdminTwoFactorCoverage();
			$administrators = $coverage->administrators();
			$this->counts = [
				'total'       => \count( $administrators ),
				'unprotected' => \count( $coverage->unprotectedAmong( $administrators ) ),
			];
		}
		return $this->counts;
	}
}

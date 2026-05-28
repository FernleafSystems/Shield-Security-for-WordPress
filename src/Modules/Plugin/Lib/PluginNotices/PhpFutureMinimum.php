<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Services\Services;

class PhpFutureMinimum extends Base {

	public const ID = 'php_future_minimum';
	public const MORE_INFO_URL = 'https://clk.shldscrty.com/shieldfutureminimumphp82';
	public const SNOOZE_USER_META = 'php_future_minimum_snoozed_at';

	private const CURRENT_MINIMUM_PHP = '7.4';
	private const FUTURE_MINIMUM_PHP = '8.2';
	private const RECOMMENDED_PHP = '8.3';
	private const SNOOZE_SECONDS = 30 * 86400;

	/**
	 * @return array{id:string,type:string,text:string[],locations:string[],can_dismiss:bool}|null
	 */
	public function check() :?array {
		if ( Services::Data()->getPhpVersionIsAtLeast( self::RECOMMENDED_PHP ) ) {
			return null;
		}

		if ( !Services::Data()->getPhpVersionIsAtLeast( self::FUTURE_MINIMUM_PHP ) ) {
			return $this->buildIssue(
				'danger',
				sprintf(
					/* translators: %1$s: current minimum PHP version, %2$s: future minimum PHP version */
					__( 'This is the final major release of Shield that supports PHP %1$s; future Shield releases will require PHP %2$s or newer.', 'wp-simple-firewall' ),
					self::CURRENT_MINIMUM_PHP,
					self::FUTURE_MINIMUM_PHP
				),
				false
			);
		}

		return $this->isSnoozed() ? null : $this->buildIssue(
			'info',
			sprintf(
				/* translators: %s: recommended PHP version */
				__( 'Your site meets the next Shield PHP requirement, but we recommend upgrading to PHP %s or newer.', 'wp-simple-firewall' ),
				self::RECOMMENDED_PHP
			),
			true
		);
	}

	public static function snoozeCurrentUser() :bool {
		$meta = self::con()->user_metas->current();
		if ( $meta === null ) {
			return false;
		}

		$meta->{self::SNOOZE_USER_META} = Services::Request()->ts();
		return true;
	}

	/**
	 * @return array{id:string,type:string,text:string[],locations:string[],can_dismiss:bool}
	 */
	private function buildIssue( string $type, string $message, bool $canDismiss ) :array {
		return [
			'id'          => self::ID,
			'type'        => $type,
			'text'        => [
				sprintf( '%s %s',
					$message,
					sprintf( '<a href="%s" class="text-reset text-decoration-underline" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( self::MORE_INFO_URL ),
						__( 'Learn more', 'wp-simple-firewall' )
					)
				),
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => $canDismiss,
		];
	}

	private function isSnoozed() :bool {
		$meta = self::con()->user_metas->current();
		$snoozedAt = $meta === null ? 0 : (int)$meta->{self::SNOOZE_USER_META};

		return $snoozedAt > 0 && Services::Request()->ts() - $snoozedAt < self::SNOOZE_SECONDS;
	}
}

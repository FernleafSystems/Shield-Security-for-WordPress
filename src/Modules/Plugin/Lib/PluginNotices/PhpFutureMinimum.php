<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\MinimumRequirements;
use FernleafSystems\Wordpress\Services\Services;

class PhpFutureMinimum extends Base {

	public const ID = 'php_future_minimum';
	public const MORE_INFO_URL = 'https://clk.shldscrty.com/helpshieldminimumrequirements';
	public const SNOOZE_USER_META = 'php_future_minimum_snoozed_at';

	protected const CURRENT_MINIMUM_PHP = MinimumRequirements::PHP;
	protected const FUTURE_MINIMUM_PHP = '';
	private const SNOOZE_SECONDS = 30 * 86400;

	/**
	 * Says only that Shield is about to stop supporting this site's PHP. That the PHP is itself out of date is a
	 * to-do in the Actions Queue.
	 * @return array{id:string,type:string,text:string[],locations:string[],can_dismiss:bool}|null
	 */
	public function check() :?array {
		// It only informs, so it waits out a new install's first day as well as a snooze.
		if ( !$this->isConfigured()
			 || Services::Data()->getPhpVersionIsAtLeast( static::FUTURE_MINIMUM_PHP )
			 || $this->isSnoozed()
			 || $this->isNewInstall() ) {
			return null;
		}

		$text = sprintf(
			/* translators: %1$s: current minimum PHP version, %2$s: future minimum PHP version */
			esc_html__( 'This is the final major release of Shield that supports PHP %1$s; future Shield releases will require PHP %2$s or newer.', 'wp-simple-firewall' ),
			static::CURRENT_MINIMUM_PHP,
			static::FUTURE_MINIMUM_PHP
		);
		if ( static::MORE_INFO_URL !== '' ) {
			$text = sprintf( '%s %s',
				$text,
				sprintf( '<a href="%s" class="text-reset text-decoration-underline" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( static::MORE_INFO_URL ),
					esc_html__( 'Learn more', 'wp-simple-firewall' )
				)
			);
		}

		return [
			'id'          => static::ID,
			'type'        => 'info',
			'text'        => [
				$text,
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => true,
		];
	}

	public static function snoozeCurrentUser() :bool {
		$meta = self::con()->user_metas->current();
		if ( $meta === null ) {
			return false;
		}

		$meta->{self::SNOOZE_USER_META} = Services::Request()->ts();
		return true;
	}

	protected function isConfigured() :bool {
		return static::FUTURE_MINIMUM_PHP !== ''
			   && \version_compare( static::FUTURE_MINIMUM_PHP, static::CURRENT_MINIMUM_PHP, '>' );
	}

	private function isSnoozed() :bool {
		$meta = self::con()->user_metas->current();
		$snoozedAt = $meta === null ? 0 : (int)$meta->{self::SNOOZE_USER_META};

		return $snoozedAt > 0 && Services::Request()->ts() - $snoozedAt < self::SNOOZE_SECONDS;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

/**
 * This form only provides a filter on the "Akismet" spam result, not a general spam result.
 *
 * Luckily the error message within the plugin is non-Akismet specific.
 *
 * Class FluentForms
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers
 */
class FluentForms extends Base {

	protected function run() {
		add_filter( 'fluentform_akismet_spam_result', function ( $wasSpam ) {
			return $wasSpam || $this->isSpam();
		}, 1000 );
	}

	protected function getProviderName() :string {
		return 'Fluent Forms';
	}

	public static function IsHandlerAvailable() :bool {
		return defined( 'FLUENTFORM' ) && @class_exists( '\FluentForm\Framework\Foundation\Bootstrap' );
	}
}
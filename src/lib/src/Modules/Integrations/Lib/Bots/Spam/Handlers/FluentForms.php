<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

/**
 * It's a little convoluted, but we use the same approach as they use for their own HoneyPot.
 * See Hooks/Ajax.php in their plugin.
 */
class FluentForms extends Base {

	protected function run() {
		\FluentForm\App::getApplication()->addAction( 'fluentform_before_insert_submission',
			function () {
				if ( $this->isSpam() ) {
					wp_send_json( [
						'errors' => $this->getCommonSpamMessage()
					], 422 );
				}
			}, 9, 0 );
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'FLUENTFORM' )
			   && @class_exists( '\FluentForm\App' )
			   && @method_exists( '\FluentForm\App', 'getApplication' )
			   && @method_exists( \FluentForm\App::getApplication(), 'addPublicAjaxAction' );
	}
}
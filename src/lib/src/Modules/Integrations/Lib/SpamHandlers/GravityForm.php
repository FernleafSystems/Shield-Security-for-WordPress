<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

class GravityForm extends Base {

	const SLUG = 'gravityforms';

	protected function run() {
		add_filter( 'gform_entry_is_spam', function ( $wasSpam ) {
			return $wasSpam || $this->isSpamBot();
		}, 1000 );
	}

	protected function getFormProvider() :string {
		return 'Gravity Forms';
	}

	protected function isPluginInstalled() :bool {
		return @class_exists( 'GFForms' ) && isset( GFForms::$version )
			   && version_compare( GFForms::$version, '2.4.17', '>=' );
	}
}
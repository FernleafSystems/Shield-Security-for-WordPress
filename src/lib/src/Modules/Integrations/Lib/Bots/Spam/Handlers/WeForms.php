<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class WeForms extends Base {

	protected function run() {
		add_filter( 'weforms_before_entry_submission',
			function ( $entry_fields ) {
				if ( $this->isBotBlockRequired() ) {
					$entry_fields = new \WP_Error( 'shield_antibot', $this->getCommonSpamMessage() );
				}
				return $entry_fields;
			},
			1000
		);
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( '\WEFORMS_VERSION' ) && \version_compare( \WEFORMS_VERSION, '1.6', '>=' );
	}
}
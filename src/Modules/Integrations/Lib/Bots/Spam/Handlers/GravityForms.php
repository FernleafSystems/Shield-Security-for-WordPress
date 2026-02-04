<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use GFCommon;

class GravityForms extends Base {

	protected function run() {
		add_filter( 'gform_entry_is_spam', function ( $wasSpam, $form ) {
			$isSpam = $wasSpam || $this->isBotBlockRequired();
			if ( $isSpam && !$wasSpam && \method_exists( 'GFCommon', 'set_spam_filter' ) ) {
				GFCommon::set_spam_filter( $form[ 'id' ], self::con()->labels->Name, $this->getCommonSpamMessage() );
			}
			return $isSpam;
		}, 1000, 2 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return isset( \GFForms::$version ) && \version_compare( \GFForms::$version, '2.4.17', '>=' );
	}
}
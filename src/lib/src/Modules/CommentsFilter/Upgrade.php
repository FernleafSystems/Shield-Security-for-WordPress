<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_900() {
		$oOpts = $this->getOptions();

		if ( $oOpts->getOpt( 'enable_google_recaptcha_comments' ) === 'N' ) {
			$oOpts->setOpt( 'google_recaptcha_style_comments', 'disabled' );
		}

		$aMap = [
			'comments_cooldown_interval'              => 'comments_cooldown',
			'comments_token_expire_interval'          => 'comments_expire',
			'enable_comments_human_spam_filter_items' => 'human_spam_items',
		];
		foreach ( $aMap as $sFrom => $sTo ) {
			$mVal = $oOpts->getOpt( $sFrom );
			if ( $mVal !== false ) {
				$oOpts->setOpt( $sTo, $mVal );
			}
		}
	}
}
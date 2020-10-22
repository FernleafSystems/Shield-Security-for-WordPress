<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Token;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Create {

	use ModConsumer;

	/**
	 * @param int $nTs
	 * @param int $nPostId
	 * @return string
	 */
	public function run( $nTs, $nPostId ) {
		/** @var CommentsFilter\Options $oOpts */
		$oOpts = $this->getOptions();

		$sToken = $this->generateNewToken( $nTs, $nPostId );

		Services::WpGeneral()->setTransient(
			$this->getCon()->prefix( 'comtok-'.md5( sprintf( '%s-%s-%s', $nPostId, $nTs, Services::IP()
																								 ->getRequestIp() ) ) ),
			$sToken,
			$oOpts->getTokenExpireInterval()
		);

		return $sToken;
	}

	/**
	 * @param int    $nTs
	 * @param string $nPostId
	 * @return string
	 */
	private function generateNewToken( $nTs, $nPostId ) {
		return hash_hmac( 'sha1',
			$nPostId.Services::IP()->getRequestIp().$nTs, $this->getCon()->getSiteInstallationId()
		);
	}
}
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\Consumers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;

trait MWPSiteConsumer {

	/**
	 * @var MWPSiteVO
	 */
	private $mwpSite;

	public function getMwpSite() :MWPSiteVO {
		return $this->mwpSite;
	}

	public function setMwpSite( MWPSiteVO $site ) :self {
		$this->mwpSite = $site;
		return $this;
	}
}
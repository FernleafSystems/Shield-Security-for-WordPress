<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class SetSingle extends SetBulk {

	/**
	 * We convert the request data to reflect that of SetBulk and call SetBulk's processor.
	 * @inheritDoc
	 */
	protected function process() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();
		$req->options = [
			[
				'key'   => $this->getWpRestRequest()->get_param( 'key' ),
				'value' => $this->getWpRestRequest()->get_param( 'value' ),
			]
		];
		return parent::process();
	}
}
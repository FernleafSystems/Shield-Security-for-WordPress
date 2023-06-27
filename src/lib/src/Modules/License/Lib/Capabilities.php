<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModConsumer;

class Capabilities {

	use ModConsumer;

	public function canMalware() :bool {
		return $this->hasCapability( 'malware_local' );
	}

	public function canMalai() :bool {
		return $this->hasCapability( 'malware_malai' );
	}

	public function canThirdPartyScanSpam() :bool {
		return $this->hasCapability( 'thirdparty_scan_spam' );
	}

	public function canThirdPartyScanUsers() :bool {
		return $this->hasCapability( 'thirdparty_scan_users' );
	}

	public function canThirdPartyActivityLog() :bool {
		return $this->hasCapability( 'thirdparty_activity_logs' );
	}

	public function canCrowdsecLevel1() :bool {
		return $this->hasCapability( 'crowdsec_level_1' );
	}

	public function canCrowdsecLevel2() :bool {
		return $this->hasCapability( 'crowdsec_level_2' );
	}

	public function canCrowdsecLevel3() :bool {
		return $this->hasCapability( 'crowdsec_level_3' );
	}

	public function canRestAPILevel1() :bool {
		return $this->hasCapability( 'rest_api_level_1' );
	}

	public function canRestAPILevel2() :bool {
		return $this->hasCapability( 'rest_api_level_2' );
	}

	public function canWpcliLevel1() :bool {
		return $this->hasCapability( 'wpcli_level_1' );
	}

	public function canWpcliLevel2() :bool {
		return $this->hasCapability( 'wpcli_level_2' );
	}

	public function canReportsLocal() :bool {
		return $this->hasCapability( 'reports_local' );
	}

	public function canReportsRemote() :bool {
		return $this->hasCapability( 'reports_remote' );
	}

	public function canMainwpLevel1() :bool {
		return $this->hasCapability( 'mainwp_level_1' );
	}

	public function canMainwpLevel2() :bool {
		return $this->hasCapability( 'mainwp_level_2' );
	}

	public function hasCapability( string $cap ) :bool {
		$license = $this->mod()->getLicenseHandler()->getLicense();
		return \in_array( $cap, $license->capabilities ) || $license->lic_version === 0;
	}
}

<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

class CrowdSecConstants {

	public const API_BASE_URL = 'https://api.crowdsec.net';
	public const SCOPE_IP = 'ip';
	public const MACHINE_ID_LENGTH = 48;
}
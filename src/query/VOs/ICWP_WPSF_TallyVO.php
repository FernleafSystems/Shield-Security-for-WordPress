<?php

/**
 * Class ICWP_WPSF_TallyVO
 * @property int    id
 * @property string stat_key
 * @property string parent_stat_key
 * @property int    tally
 * @property int    created_at
 * @property int    modified_at
 * @property int    deleted_at
 */
class ICWP_WPSF_TallyVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter {
		__get as __parentGet;
	}
}
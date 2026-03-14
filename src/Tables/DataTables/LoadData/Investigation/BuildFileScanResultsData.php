<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForFileScanResults;
use FernleafSystems\Wordpress\Services\Services;

class BuildFileScanResultsData extends BaseScanResultsInvestigationData {

	protected function getSubjectWheres() :array {
		switch ( $this->subjectType ) {
			case 'plugin':
				$wheres = InvestigationSubjectWheres::forAssetSlug( (string)$this->subjectId );
				break;
			case 'theme':
				$slug = (string)$this->subjectId;
				$theme = Services::WpThemes()->getThemeAsVo( $slug );
				if ( !empty( $theme ) ) {
					$slug = (string)$theme->stylesheet;
				}
				$wheres = InvestigationSubjectWheres::forAssetSlug( $slug );
				break;
			case 'core':
				$wheres = InvestigationSubjectWheres::forCoreResults();
				break;
			default:
				$wheres = InvestigationSubjectWheres::impossible();
				break;
		}
		return $wheres;
	}

	protected function getInvestigationTableBuilderClass() :string {
		return ForFileScanResults::class;
	}
}

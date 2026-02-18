import {
	Chart as ChartJS,
	DoughnutController,
	ArcElement
} from 'chart.js';

ChartJS.register( DoughnutController, ArcElement );

export function renderHeroGauge( canvas, {
	percentage = 0,
	rgbs = '',
	thresholds = {}
} = {} ) {
	if ( !canvas ) {
		return null;
	}

	const safePercentage = Math.max( 0, Math.min( 100, parseInt( percentage, 10 ) || 0 ) );
	const { mainColor, bgColor } = resolveHeroGaugeColors( safePercentage, rgbs, thresholds );

	return new ChartJS( canvas, {
		type: 'doughnut',
		data: {
			datasets: [ {
				data: [ safePercentage, 100 - safePercentage ],
				backgroundColor: [ mainColor, bgColor ],
				borderWidth: 0,
				borderRadius: [ 6, 0 ],
			} ]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			rotation: -90,
			circumference: 180,
			cutout: '75%',
			plugins: {
				legend: { display: false },
				tooltip: { enabled: false },
			},
			animation: {
				animateRotate: true,
				duration: 1200,
				easing: 'easeOutQuart',
			}
		}
	} );
}

function resolveHeroGaugeColors( percentage, rgbsRaw, thresholds = {} ) {
	const rgbParts = String( rgbsRaw )
	.split( ',' )
	.map( ( value ) => parseInt( value, 10 ) );

	if ( rgbParts.length >= 3 && rgbParts.slice( 0, 3 ).every( ( value ) => Number.isInteger( value ) ) ) {
		return {
			mainColor: `rgb(${rgbParts[ 0 ]},${rgbParts[ 1 ]},${rgbParts[ 2 ]})`,
			bgColor: `rgba(${rgbParts[ 0 ]},${rgbParts[ 1 ]},${rgbParts[ 2 ]},0.12)`,
		};
	}

	const t = {
		good: parseInt( thresholds.good, 10 ) || 70,
		warning: parseInt( thresholds.warning, 10 ) || 40,
	};

	if ( percentage > t.good ) {
		return {
			mainColor: '#008000',
			bgColor: 'rgba(0,128,0,0.12)',
		};
	}
	if ( percentage > t.warning ) {
		return {
			mainColor: '#edb41d',
			bgColor: 'rgba(237,180,29,0.12)',
		};
	}
	return {
		mainColor: '#c62f3e',
		bgColor: 'rgba(198,47,62,0.12)',
	};
}

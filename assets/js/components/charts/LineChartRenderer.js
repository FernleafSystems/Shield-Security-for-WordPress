import {
	CategoryScale,
	Chart as ChartJS,
	LineController,
	LineElement,
	LinearScale,
	PointElement,
	Tooltip
} from 'chart.js';

ChartJS.register( LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip );

const CHART_PALETTE = [
	'#0d6efd',
	'#198754',
	'#fd7e14',
	'#6f42c1',
	'#dc3545',
	'#20c997',
	'#6610f2'
];

export class LineChartRenderer {

	constructor( outputEl, legendEl = null ) {
		this.outputEl = outputEl;
		this.legendEl = legendEl;
		this.chart = null;
		this.canvas = null;
	}

	clear() {
		if ( this.chart ) {
			this.chart.destroy();
			this.chart = null;
		}
		if ( this.outputEl ) {
			this.outputEl.innerHTML = '';
		}
		if ( this.legendEl ) {
			this.legendEl.innerHTML = '';
		}
		this.canvas = null;
	}

	render( chartData = {} ) {
		if ( !this.outputEl ) {
			return;
		}

		this.clear();
		this.canvas = document.createElement( 'canvas' );
		this.canvas.style.width = '100%';
		this.canvas.style.height = '100%';
		this.outputEl.appendChild( this.canvas );

		const datasets = ( chartData.series || [] ).map( ( series, index ) => {
			const color = CHART_PALETTE[ index % CHART_PALETTE.length ];
			return {
				label: series.label,
				data: series.data || [],
				borderColor: color,
				backgroundColor: this.hexToRgba( color, 0.15 ),
				borderWidth: 2,
				pointBackgroundColor: color,
				pointBorderColor: color,
				pointRadius: 2,
				pointHoverRadius: 4,
				pointHitRadius: 8,
				tension: 0.2,
				fill: false
			};
		} );

		this.chart = new ChartJS( this.canvas, {
			type: 'line',
			data: {
				labels: chartData.labels || [],
				datasets,
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'nearest',
					axis: 'x',
					intersect: false,
				},
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						enabled: true,
					},
				},
				scales: {
					x: {
						grid: {
							display: false,
						},
						ticks: {
							maxRotation: 0,
							autoSkip: true,
						},
					},
					y: {
						beginAtZero: true,
						ticks: {
							maxTicksLimit: 8,
							precision: 0,
							callback: ( value ) => Number.isFinite( Number( value ) )
								? Math.round( Number( value ) ).toLocaleString()
								: value,
						},
					},
				},
			},
		} );

		this.renderLegend( datasets );
	}

	renderLegend( datasets ) {
		if ( !this.legendEl ) {
			return;
		}

		const legendWrap = this.legendEl.closest( '[data-reports-chart-legend-wrap="1"]' );
		this.legendEl.innerHTML = '';
		if ( datasets.length <= 1 ) {
			legendWrap?.classList.add( 'd-none' );
			return;
		}

		legendWrap?.classList.remove( 'd-none' );
		datasets.forEach( ( dataset ) => {
			const item = document.createElement( 'span' );
			item.className = 'shield-reports-trends-legend-item';

			const swatch = document.createElement( 'span' );
			swatch.className = 'shield-reports-trends-legend-swatch';
			swatch.style.background = dataset.borderColor;

			const label = document.createElement( 'span' );
			label.textContent = dataset.label;

			item.appendChild( swatch );
			item.appendChild( label );
			this.legendEl.appendChild( item );
		} );
	}

	hexToRgba( hex, alpha ) {
		const cleaned = String( hex || '' ).replace( '#', '' );
		if ( cleaned.length !== 6 ) {
			return `rgba(0, 128, 0, ${alpha})`;
		}

		const r = Number.parseInt( cleaned.slice( 0, 2 ), 16 );
		const g = Number.parseInt( cleaned.slice( 2, 4 ), 16 );
		const b = Number.parseInt( cleaned.slice( 4, 6 ), 16 );
		return `rgba(${r}, ${g}, ${b}, ${alpha})`;
	}
}

const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const CssMinimizerPlugin = require( "css-minimizer-webpack-plugin" );
const TerserPlugin = require( "terser-webpack-plugin" );
// const BrowserSyncPlugin = require( 'browser-sync-webpack-plugin' );
const path = require( 'path' );
const autoprefixer = require( 'autoprefixer' )

// change these variables to fit your project
const pathResources = './assets';
// const jsPath = pathResources + '/js';
// const cssPath = pathResources + '/css';
const outputPath = './assets/dist';

module.exports = {
	mode: "production",
	entry: {
		'shield-main': pathResources + '/plugin-main.js',
		'shield-badge': pathResources + '/plugin-badge.js',
		'shield-blockpage': pathResources + '/plugin-blockpage.js',
		'shield-login_2fa': pathResources + '/plugin-login_2fa.js',
		'shield-notbot': pathResources + '/plugin-notbot.js',
		'shield-reports': pathResources + '/plugin-reports.js',
		'shield-wpadmin': pathResources + '/plugin-wpadmin.js',
		'shield-userprofile': pathResources + '/plugin-userprofile.js',
		'shield-mainwp_server': pathResources + '/plugin-mainwp_server.js',
	},
	output: {
		path: path.resolve( __dirname, outputPath ),
		filename: '[name].bundle.js',
		clean: true,
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: '[name].bundle.css',
		} ),

		// Uncomment this if you want to use CSS Live reload
		/*
		new BrowserSyncPlugin({
		  proxy: localDomain,
		  files: [ outputPath + '/*.css' ],
		  injectCss: true,
		}, { reload: false, }),
		*/
	],
	module: {
		rules: [
			{
				test: /\.scss$/i,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					{
						loader: 'sass-loader',
						options: {
							sassOptions: {
								// Suppress deprecation warnings for now
								// TODO: Update to modern Sass module system in future
								quietDeps: true,
								silenceDeprecations: ['import'],
							},
						},
					},
				],
			},
			{
				test: /\.js$/i,
				exclude: /node_modules/,
				use: {
					loader: "babel-loader",
					options: {
						presets: [ '@babel/preset-env' ]
					}
				},
			},
			{
				test: /\.(jpg|jpeg|png|gif|woff|woff2|eot|ttf|svg)$/i,
				use: {
					loader: "file-loader",
					options: {
						name: "[name].[ext]",
						outputPath: "img",
					}
				},
			},
			// {
			// 	// Loader for webpack to process CSS with PostCSS
			// 	test: /\.s?css$/i,
			// 	loader: 'postcss-loader',
			// 	options: {
			// 		postcssOptions: {
			// 			plugins: [
			// 				autoprefixer
			// 			]
			// 		}
			// 	}
			// },
			// {
			// 	test: /\.sass$/i,
			// 	use: [
			// 		MiniCssExtractPlugin.loader,
			// 		'css-loader',
			// 		{
			// 			loader: 'sass-loader',
			// 			options: {
			// 				sassOptions: { indentedSyntax: true },
			// 			},
			// 		},
			// 	],
			// },
			// {
			// 	test: /\.(jpg|jpeg|png|gif|woff|woff2|eot|ttf|svg)$/i,
			// 	use: 'url-loader?limit=1024',
			// },
		]
	},
	optimization: {
		minimizer: [
			new CssMinimizerPlugin(), new TerserPlugin()
		],
	},
	externals: {
		"jquery": "jQuery",
		"jquery-ui": "jquery-ui/jquery-ui.js",
	}
};
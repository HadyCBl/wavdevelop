const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const { WebpackManifestPlugin } = require("webpack-manifest-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const CompressionPlugin = require("compression-webpack-plugin");

module.exports = (env = {}) => {
  const isProduction = env.mode === 'production';
  const isDevelopment = !isProduction;
  
  const entries = {
    another: "./includes/js/bb_anothermodules.js",
    caja: "./includes/js/bb_caja.js",
    shared: "./includes/js/bb_shared.js",
    otros_ingresos: "./includes/js/bb_otrosingresos.js",
    compras_ventas: "./includes/js/bb_comprasventas.js",
    reportes: "./includes/js/bb_reportes.js",
  };

  let selectedEntries = entries;
  if (env.target) {
    if (!entries[env.target]) {
      throw new Error(`Entrada no encontrada: ${env.target}`);
    }
    selectedEntries = {
      [env.target]: entries[env.target],
    };
  }

  return {
    mode: isProduction ? "production" : "development",
    entry: selectedEntries,
    
    output: {
      filename: isProduction 
        ? "js/bundle_[name].[contenthash:8].js"
        : "js/bundle_[name].js",
      path: path.resolve(__dirname, "public/assets/dist"),
      clean: true,
      publicPath: "/public/assets/dist/",
      assetModuleFilename: isProduction
        ? "images/[name].[contenthash:8][ext]"
        : "images/[name][ext]",
    },

    devtool: isProduction ? false : 'eval-source-map',

    optimization: {
      minimize: isProduction,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            parse: {
              ecma: 8,
            },
            compress: {
              ecma: 5,
              warnings: false,
              comparisons: false,
              inline: 2,
              drop_console: isProduction,
              drop_debugger: isProduction,
              pure_funcs: isProduction ? ['console.log', 'console.info', 'console.debug'] : [],
            },
            mangle: {
              safari10: true,
              toplevel: true,
              eval: true,
              keep_classnames: false,
              keep_fnames: false,
            },
            output: {
              ecma: 5,
              comments: false,
              ascii_only: true,
              beautify: false,
            },
          },
          extractComments: false,
          parallel: true,
        }),
        
        new CssMinimizerPlugin({
          minimizerOptions: {
            preset: [
              'default',
              {
                discardComments: { removeAll: true },
              },
            ],
          },
        }),
      ],

      splitChunks: isProduction ? {
        chunks: 'all',
        cacheGroups: {
          // Vendor base (código común de node_modules JS)
          vendor: {
            test: /[\\/]node_modules[\\/](?!(jquery|alpinejs|@alpinejs|datatables\.net)).*[\\/]/,
            name: 'vendors',
            priority: 10,
            reuseExistingChunk: true,
            enforce: true,
          },
          
          // jQuery separado
          jquery: {
            test: /[\\/]node_modules[\\/]jquery[\\/]/,
            name: 'jquery',
            priority: 25,
            reuseExistingChunk: true,
            enforce: true,
          },
          
          // Alpine.js separado
          alpine: {
            test: /[\\/]node_modules[\\/](alpinejs|@alpinejs)[\\/]/,
            name: 'alpine',
            priority: 20,
            reuseExistingChunk: true,
            enforce: true,
          },
          
          // DataTables separado
          datatables: {
            test: /[\\/]node_modules[\\/]datatables\.net[\\/]/,
            name: 'datatables',
            priority: 15,
            reuseExistingChunk: true,
            enforce: true,
          },
          
          // CSS de vendors (librerías externas)
          vendorsStyles: {
            test: /[\\/]node_modules[\\/].*\.css$/,
            name: 'vendors',
            type: 'css/mini-extract',
            priority: 12,
            reuseExistingChunk: true,
            enforce: true,
          },
          
          // CSS común entre tus bundles
          commonStyles: {
            test: /\.css$/,
            name: 'common',
            type: 'css/mini-extract',
            minChunks: 2,
            priority: 8,
            reuseExistingChunk: true,
          },
          
          // Código JS común entre tus propios módulos
          common: {
            minChunks: 2,
            priority: 5,
            reuseExistingChunk: true,
          },
        },
      } : false,

      runtimeChunk: isProduction ? 'single' : false,
    },

    module: {
      rules: [
        {
          test: /\.m?js$/,
          exclude: /node_modules/,
          use: {
            loader: "babel-loader",
            options: {
              presets: [
                [
                  "@babel/preset-env",
                  {
                    modules: false,
                    useBuiltIns: false,
                  }
                ]
              ],
              cacheDirectory: true,
            },
          },
        },
        {
          test: /\.css$/i,
          use: [
            MiniCssExtractPlugin.loader,
            {
              loader: "css-loader",
              options: {
                importLoaders: 1,
                sourceMap: isDevelopment,
              },
            },
            {
              loader: "postcss-loader",
              options: {
                postcssOptions: {
                  plugins: [
                    require("autoprefixer")({
                      overrideBrowserslist: ["last 2 versions"],
                    }),
                  ],
                },
                sourceMap: isDevelopment,
              },
            },
          ],
        },
        {
          test: /\.(png|svg|jpg|jpeg|gif)$/i,
          type: "asset",
          parser: {
            dataUrlCondition: {
              maxSize: 8 * 1024,
            },
          },
        },
        {
          test: /\.(woff|woff2|eot|ttf|otf)$/i,
          type: "asset/resource",
        },
      ],
    },

    plugins: [
      new MiniCssExtractPlugin({
        filename: isProduction
          ? "css/bundle_[name].[contenthash:8].css"
          : "css/bundle_[name].css",
        chunkFilename: isProduction
          ? "css/bundle_[name].[contenthash:8].css"
          : "css/bundle_[name].css",
      }),

      new WebpackManifestPlugin({
        fileName: 'manifest.json',
        publicPath: '/public/assets/dist/',
        basePath: '',
        writeToFileEmit: true,
        filter: (file) => {
          return !file.name.endsWith('.map');
        },
        map: (file) => {
          const name = file.name.replace(/\.[a-f0-9]{8}\./, '.');
          return {
            ...file,
            name: name,
          };
        },
      }),

      ...(isProduction ? [
        new CompressionPlugin({
          filename: '[path][base].gz',
          algorithm: 'gzip',
          test: /\.(js|css|html|svg)$/,
          threshold: 8192,
          minRatio: 0.8,
        }),
        
        // new CompressionPlugin({
        //   filename: '[path][base].br',
        //   algorithm: 'brotliCompress',
        //   test: /\.(js|css|html|svg)$/,
        //   compressionOptions: {
        //     level: 11,
        //   },
        //   threshold: 8192,
        //   minRatio: 0.8,
        // }),
      ] : []),
    ],

    performance: {
      hints: isProduction ? 'warning' : false,
      maxEntrypointSize: 512000,
      maxAssetSize: 512000,
    },

    target: "web",
    
    stats: isDevelopment ? "errors-warnings" : {
      colors: true,
      hash: true,
      version: true,
      timings: true,
      assets: true,
      chunks: false,
      modules: false,
      children: false,
    },

    cache: {
      type: 'filesystem',
      cacheDirectory: path.resolve(__dirname, '.webpack-cache'),
    },
  };
};
const path = require('path');
const UglifyJSPlugin = require('uglifyjs-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const FixStyleOnlyEntriesPlugin = require("webpack-fix-style-only-entries");

module.exports ={ 
    entry : {
        './assets/js/upn.app.admin' : './assets/js/src/app.js',
        './assets/js/upn.admin.global' : './assets/js/src/app.global.js',
        './assets/css/upn-admin-style' : './assets/css/src/app.scss',
        './assets/plugins/firebase/js/firebase.init' : './assets/plugins/firebase/src/init_notifications.js',
        './assets/plugins/firebase/js/firebase.messaging.sw' : './assets/plugins/firebase/src/firebase-messaging-sw.js'
    },
    output : {
        filename : '[name].min.js',
        path : path.resolve(__dirname)
    },
    module : {
        rules : [
            {
                test : /\.js$/,
                exclude: /node_modules/,
                use : {
                    loader: 'babel-loader',
                    options : {
                        presets: ['@babel/preset-env']
                    }
                }
            },
            {
                test: /\.(sass|scss)$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'postcss-loader',
                    'sass-loader'
                ]
            }
        ]
    },
    plugins: [ 
        new FixStyleOnlyEntriesPlugin(),
        new MiniCssExtractPlugin({
            filename: '[name].min.css'
        })
    ],
    optimization :{
        minimizer : [
            new UglifyJSPlugin({
                cache : true,
                parallel : true
            }),
            new OptimizeCSSAssetsPlugin({})
        ]
    }

};

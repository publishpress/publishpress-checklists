const NODE_ENV = process.env.NODE_ENV || 'development';

let path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode: NODE_ENV,
    entry: {
        'gutenberg-warning': './modules/checklists/assets/js/gutenberg-warning.jsx',
        'gutenberg-panel': './modules/checklists/assets/js/gutenberg-panel.jsx',
    },
    output: {
        path: path.join(__dirname, 'modules/checklists/assets/js'),
        filename: '[name].min.js'
    },
    optimization: {
        minimize: NODE_ENV === 'production',
        minimizer: [
            new TerserPlugin({
                extractComments: false,
                terserOptions: {
                    format: {
                        comments: false
                    }
                }
            })
        ]
    },
    module: {
        rules: [
            {
                test: /.jsx$/,
                exclude: /node_modules/,
                loader: 'babel-loader'
            }
        ]
    }
};

const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const webpack = require('webpack');
module.exports = {
  entry: './assets-pdf/index.js',
  output: {
    path: path.resolve(__dirname, 'public/build'),
    filename: 'js/bundle.js'
  },
  devtool: false,
  watch: true,
  module: {
    rules: [{
      test: /\.scss$/,
      use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
                //sourceMap: true,
                // options...
              }
          },
          {
            loader: 'sass-loader',
            options: {
              //sourceMap: true,
              // options...
            }
          }
        ]
    }]
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: 'css/pdf.css'
    }),
    //new webpack.SourceMapDevToolPlugin({})
  ]
};
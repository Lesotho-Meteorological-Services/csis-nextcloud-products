const path = require('path')
const baseConfig = require('@nextcloud/webpack-vue-config/webpack.config.js')

module.exports = {
	...baseConfig,
	entry: {
		init: path.resolve(path.join('src', 'init.js')),
	},
}

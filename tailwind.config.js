/** @type {import('tailwindcss').Config} */

module.exports = {
	content: [
		"./assets/**/*.js",
		"./templates/**/*.html.twig"
	],
	theme: {
		backgroundImage: {
			landing: "url('/public/resources/images/wallpaper.png')"
		},

		colors: {
			white: '#F5F5F5',

			primary: '#A0C66B',
		},

		fontFamily: {
			sans: ["Inter, sans-serif"],
		},

		extend: {},
	},
	plugins: [],
}

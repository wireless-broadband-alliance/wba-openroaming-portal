/** @type {import('tailwindcss').Config} */

module.exports = {
	content: [
		"./assets/**/*.js",
		"./templates/**/*.html.twig"
	],
	theme: {
		extend: {


			colors: {
				gray: {
					100: '#F5F5F5',
				},

				primary: '#A0C66B',
			},

			fontFamily: {
				sans: ["Inter var, sans-serif"],
				roboto: ["Roboto, mono"],
			},
		},
	},
	plugins: [],
}

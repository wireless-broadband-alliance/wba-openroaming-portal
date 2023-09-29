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
				veryDarkPurple: '#594B60',
				veryDarkButton: '#1C0523',
			},

			fontFamily: {
				sans: ["Inter var, sans-serif"],
				roboto: ["Roboto, mono"],
			},
		},
	},
	plugins: [],
}

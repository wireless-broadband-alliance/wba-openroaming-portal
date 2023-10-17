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
				veryDarkButton: '#232B35',
				lightGreen: '#57a475',
				veryLightGreen: '#f1f8f4',
				lightGray: '#f7f6f8',
				inputGray: '#D9D9D9',
				lightBlue: "#DFEBFC",
				textBlue: "#1D65A7"

			},

			fontFamily: {
				sans: ["Inter var, sans-serif"],
				roboto: ["Roboto, mono"],
			},
		},
	},
	plugins: [],
}

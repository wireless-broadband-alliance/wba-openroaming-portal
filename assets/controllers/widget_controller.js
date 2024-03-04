import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function showWidgets(buttonName, menuName) {
			const dropdownButtonItems = document.getElementsByName(buttonName);
			const dropdownMenuItems = document.getElementsByName(menuName);

			// Loop through all elements with the given name and attach event listeners
			dropdownButtonItems.forEach((button, index) => {
				button.addEventListener('click', () => {
					const dropdownMenu = dropdownMenuItems[index];
					if (dropdownMenu.style.display === 'block') {
						dropdownMenu.style.display = 'none';
					} else {
						dropdownMenu.style.display = 'block';
					}
				});
			});

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				dropdownButtonItems.forEach((button, index) => {
					const dropdownMenu = dropdownMenuItems[index];
					if (!button.contains(event.target) && !dropdownMenu.contains(event.target)) {
						dropdownMenu.style.display = 'none';
					}
				});
			});
		}

		showWidgets('widgetUserButton', 'widgetUser');
	}
}

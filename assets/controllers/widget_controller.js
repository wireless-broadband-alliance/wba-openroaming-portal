import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		function showWidgets(buttonId, menuId) {
			const widgetButton = document.getElementById(buttonId);
			const widgetItem = document.getElementById(menuId);

			widgetButton.addEventListener('click', () => {
				// Toggle the display of the widgetItem
				if (widgetItem.style.display === 'block') {
					widgetItem.style.display = 'none';
					widgetButton.classList.remove('focus:bg-[#7DB928]', 'focus:text-white');
				} else {
					widgetItem.style.display = 'block';
					widgetButton.classList.add('focus:bg-[#7DB928]', 'focus:text-white');
				}
			});

			// Close the widget when clicking outside it
			document.addEventListener('click', (event) => {
				// Check if the click is outside the button and the widgetItem
				if (!widgetButton.contains(event.target) && !widgetItem.contains(event.target)) {
					widgetItem.style.display = 'none';
				}
			});
		}

		showWidgets('widgetUserButton', 'widgetUser');
	}
}

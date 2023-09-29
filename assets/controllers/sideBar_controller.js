import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const sidebarBtnElement = document.getElementById('sidebarButton');
		const sidebarElement = document.getElementById('sidebar');
		const dropdownButton = document.getElementById('dropdownButton');
		const optionsSidebarButton = document.getElementById('optionsDropdownButton');

		const alertElement = document.getElementById('alert-2');
		if (alertElement) {
			alertElement.addEventListener('animationend', () => {
				alertElement.classList.add('hidden');
			});
		}

		sidebarBtnElement.addEventListener('click', () => {
			sidebarBtnElement.classList.toggle('open');
			sidebarElement.classList.toggle('hidden');
		});


		const mediaQuery = window.matchMedia('(max-width: 768px)');

		function handleResolutionChange(mediaQuery) {
			if (mediaQuery.matches) {
				sidebarElement.classList.add('absolute');
				sidebarElement.classList.remove('relative');
				dropdownButton.classList.remove('relative');
				dropdownButton.classList.add('hidden');
				optionsSidebarButton.classList.remove('hidden');
				optionsSidebarButton.classList.add('relative');
			} else {
				sidebarElement.classList.remove('absolute');
				sidebarElement.classList.add('relative');
				dropdownButton.classList.remove('hidden');
				dropdownButton.classList.add('relative');
			}
		}

		// Initial check on a page load
		handleResolutionChange(mediaQuery);

		// Listen for resolution changes
		mediaQuery.addListener(handleResolutionChange);
	}
}

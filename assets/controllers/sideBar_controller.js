import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const sidebarBtnElement = document.getElementById('sidebarButton');
		const sidebarElement = document.getElementById('sidebar');
		const dropdownButton = document.getElementById('adminActionsDropdownButton');
		const optionsSidebarButton = document.getElementById('optionsDropdownButton');
		const customCards = document.getElementsByName('customCards');
		const bodyElement = document.body; // Get the body element

		const alertElement = document.getElementById('alert-2');
		if (alertElement) {
			alertElement.addEventListener('animationend', () => {
				alertElement.classList.add('hidden');
			});
		}

		sidebarBtnElement.addEventListener('click', () => {
			sidebarBtnElement.classList.toggle('open');
			sidebarElement.classList.toggle('hidden');

			if (sidebarElement.classList.contains('hidden')) {
				bodyElement.classList.remove('overflow-hidden');
			} else {
				bodyElement.classList.add('overflow-hidden');
			}
		});


		const mediaQuery = window.matchMedia('(max-width: 1536px)');

		// Function to handle changes in resolution
		function handleResolutionChange(mediaQuery) {
			if (mediaQuery.matches) {
				sidebarElement.classList.add('hidden');
				sidebarElement.classList.add('absolute');
				sidebarElement.classList.remove('relative');
				dropdownButton.classList.remove('relative');
				dropdownButton.classList.add('hidden');
				optionsSidebarButton.classList.remove('hidden');
				optionsSidebarButton.classList.add('relative');

				customCards.forEach((description_target, index) => {
					customCards[index].classList.remove('hover:-translate-y-1');
				});

			} else {
				sidebarElement.classList.remove('hidden');
				sidebarElement.classList.remove('absolute');
				sidebarElement.classList.add('relative');
				dropdownButton.classList.remove('hidden');
				dropdownButton.classList.add('relative');
				optionsSidebarButton.classList.remove('relative');
				optionsSidebarButton.classList.add('hidden');
				customCards.forEach((description_target, index) => {
					customCards[index].classList.add('hover:-translate-y-1');
				});
			}
		}

		// Initial check on a page load
		handleResolutionChange(mediaQuery);

		// Listen for resolution changes
		mediaQuery.addListener(handleResolutionChange);


		function initializeDropdown_AdminActions(buttonId, menuId) {
			const dropdownButton = document.getElementById(buttonId);
			const dropdownMenu = document.getElementById(menuId);

			dropdownButton.addEventListener('click', () => {
				if (dropdownMenu.style.display === 'block') {
					dropdownMenu.style.display = 'none';
					dropdownButton.classList.remove('bg-veryDarkButton', 'text-white');
				} else {
					dropdownMenu.style.display = 'block';
					dropdownButton.classList.add('bg-veryDarkButton', 'text-white');
				}
			});

			// Close the dropdown when clicking outside it
			document.addEventListener('click', (event) => {
				if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
					dropdownMenu.style.display = 'none';
					dropdownButton.classList.remove('bg-veryDarkButton', 'text-white');
				}
			});
		}

		initializeDropdown_AdminActions('adminActionsDropdownButton', 'adminActionsDropdown');


		function initializeDropdown_Select(buttonId, menuId) {
			const dropdownButtonSelect = document.getElementById(buttonId);
			const dropdownMenuSelect = document.getElementById(menuId);

			dropdownButtonSelect.addEventListener('click', () => {
				if (dropdownMenuSelect.style.display === 'block') {
					dropdownMenuSelect.style.display = 'none';
				} else {
					dropdownMenuSelect.style.display = 'block';
				}
			});
		}
	
		initializeDropdown_Select('PortalDropdownButton', 'PortalDropDown');
		initializeDropdown_Select('StatisticsDropdownButton', 'StatisticsDropDown');
		initializeDropdown_Select('optionsDropdownButton', 'optionsDropdown');

		function initializeDropdown_ActionItems(buttonName, menuName) {
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

		initializeDropdown_ActionItems('itemsDropDownButton', 'itemsDropDown');

		function remove_element(elementId) {
			setTimeout(function () {
				let errorContainer = document.getElementById(elementId);
				if (errorContainer) {
					errorContainer.style.transition = 'opacity 0.5s';
					errorContainer.style.opacity = '0';
					setTimeout(function () {
						errorContainer.remove();
					}, 500); // Wait for the transition to finish and then remove the element
				}
			}, 5000); // set a time of five seconds and then remove the error
		}

		remove_element('errorDisplay');
		remove_element('successDisplay');

		function confirmDeletePost(userId, userUuid) {
			if (confirm('Are you sure you want to delete the user with UUID ' + userUuid + '?')) {
			document.getElementById('deleteForm' + userId).submit();
			}
		}

		document.addEventListener('DOMContentLoaded', function () {
			const deleteButtons = document.querySelectorAll('.delete-users-button');

			deleteButtons.forEach(button => {
				button.addEventListener('click', () => {
					const userId = button.getAttribute('data-user-id');
					const userUuid = button.getAttribute('data-user-uuid');

					if (confirm(`Are you sure you want to delete the user with UUID ${userUuid}?`)) {
						document.getElementById('deleteForm' + userId).submit();
					}
				});
			});
		});

		function addSortingClickListener(columnName, activeSort, activeOrder) {
			var columnElement = document.getElementById(columnName + 'Column');
		
			if (columnElement) {
				columnElement.addEventListener('click', function () {
					// Retrieve the current URL
					var currentUrl = new URL(window.location.href);
		
					// Parse the URL parameters into an object
					var urlParams = new URLSearchParams(currentUrl.search);
		
					// Check if the 'sort' parameter is present and matches the current column
					if (urlParams.has('sort') && urlParams.get('sort') === columnName) {
						// If yes, toggle the sorting order
						var newOrder = urlParams.get('order') === 'asc' ? 'desc' : 'asc';
						urlParams.set('order', newOrder);
					} else {
						// If not, set the sorting parameters for the current column
						urlParams.set('sort', columnName);
						urlParams.set('order', 'asc');
					}
		
					// Update the URL with the new parameters
					currentUrl.search = '?' + urlParams.toString();
		
					// Redirect to the new URL
					window.location.href = currentUrl.toString();
				});
				
				// Add a class to indicate the active sort column
				if (activeSort === columnName) {
					columnElement.classList.add('active-sort');
					// Optionally, add a class for the active sort order (asc/desc)
					columnElement.classList.toggle('asc', activeOrder === 'asc');
					columnElement.classList.toggle('desc', activeOrder === 'desc');
				}
			}
		}
		
		addSortingClickListener('uuid', '{{ activeSort }}', '{{ activeOrder }}');
		addSortingClickListener('createdAt', '{{ activeSort }}', '{{ activeOrder }}');					
	}
}

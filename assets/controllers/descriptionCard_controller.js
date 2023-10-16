import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const description_values = document.getElementsByName("description");
		const description_targets = document.getElementsByName("descriptionIcon");

		description_targets.forEach((description_target, index) => {
			let timeout; // Initialize a timeout variable

			description_target.addEventListener('mouseover', function handleMouseOver() {
				// Delay showing the description box for 500 milliseconds (adjust as needed)
				timeout = setTimeout(() => {
					description_values[index].classList.remove('hidden');
					description_values[index].classList.add('opacity-100');
				}, 500);
			});

			description_target.addEventListener('mouseout', function handleMouseOut() {
				clearTimeout(timeout); // Clear the timeout if the user moves the mouse out before the delay
				description_values[index].classList.add('hidden');
				description_values[index].classList.remove('opacity-100');
			});
		});

		const imageContainers = document.querySelectorAll('.image-container');
		imageContainers.forEach((container) => {
			const imagePreview = container.querySelector('[data-image-preview]');
			const imageInput = container.querySelector('[data-image-input]');
			// Add and event for every time the upload button is clicked and to change the current image in display

			imageInput.addEventListener('change', (e) => {
				this.handleImageChange(e, imagePreview);
			});
			const uploadButton = container.querySelector('.w-28.h-12');
			uploadButton.addEventListener('click', () => {
				imageInput.click();
			});
		});

		// Listen to changes in the select input of the capport form
		const capportMessage = document.getElementById('capportMessage');
		const capportEnabledSelect = document.querySelector('[name="capport[CAPPORT_ENABLED]"]');
		const capportPortalUrlInput = document.querySelector('[name="capport[CAPPORT_PORTAL_URL]"]');
		const capportVenueInfoUrlInput = document.querySelector('[name="capport[CAPPORT_VENUE_INFO_URL]"]');
		const capportPortalUrlCard = document.getElementById('CAPPORT_PORTAL_URL');
		const capportVenueInfoUrlCard = document.getElementById('CAPPORT_VENUE_INFO_URL');
		console.log(capportVenueInfoUrlCard)

		if (capportEnabledSelect) {
			const toggleMessageAndInputState = () => {
				const capportEnabledValue = capportEnabledSelect.value;

				// Check if the value is "true" and show or hide the message accordingly
				if (capportEnabledValue === 'true') {
					capportMessage.classList.remove('hidden');
					// Enable the input fields
					capportPortalUrlInput.disabled = false;
					capportVenueInfoUrlInput.disabled = false;
					// Remove the style classes indicating that the inputs are blocked
					capportPortalUrlInput.classList.remove('bg-gray-400', 'cursor-not-allowed');
					capportVenueInfoUrlInput.classList.remove('bg-gray-400', 'cursor-not-allowed');
					capportPortalUrlCard.classList.add('bg-white');
					capportVenueInfoUrlCard.classList.add('bg-red');
					capportPortalUrlCard.classList.remove('bg-red-500');
					capportVenueInfoUrlCard.classList.remove('bg-red-500');
				} else {
					capportMessage.classList.add('hidden');
					// Disable the input fields
					capportPortalUrlInput.disabled = true;
					capportVenueInfoUrlInput.disabled = true;
					// Add style classes to indicate that the inputs are blocked and make the cards appear darker
					capportPortalUrlInput.classList.add('cursor-not-allowed');
					capportVenueInfoUrlInput.classList.add('cursor-not-allowed');
					capportPortalUrlCard.classList.remove('bg-white');
					capportVenueInfoUrlCard.classList.remove('bg-white');
					capportPortalUrlCard.classList.add('bg-red-500');
					capportVenueInfoUrlCard.classList.add('bg-red-500');
				}
			};

			// Initial check when the page loads
			toggleMessageAndInputState();

			// Listen to input changes and update the message and input state
			capportEnabledSelect.addEventListener('input', toggleMessageAndInputState);
		}
	}

	// Loads the uploaded image from the cache
	handleImageChange(e, imagePreview) {
		const file = e.target.files[0];

		if (file) {
			const reader = new FileReader();

			reader.onload = (event) => {
				imagePreview.src = event.target.result;
			};
			reader.readAsDataURL(file);
		}
	}
}

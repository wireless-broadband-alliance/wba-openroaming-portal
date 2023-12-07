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
			const uploadButton = container.querySelector('.upload-button');
			uploadButton.addEventListener('click', () => {
				imageInput.click();
			});
		});
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

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
	}
}

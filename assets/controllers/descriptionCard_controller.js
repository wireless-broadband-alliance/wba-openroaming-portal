import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		const description_values = document.getElementsByName("description");
		const description_targets = document.getElementsByName("descriptionIcon");

		description_targets.forEach((description_target, index) => {
			description_target.addEventListener('mouseover', function handleMouseOver() {
				description_values[index].style.display = 'block';
			});

			description_target.addEventListener('mouseout', function handleMouseOut() {
				description_values[index].style.display = 'none';
			});
		});
	}
}

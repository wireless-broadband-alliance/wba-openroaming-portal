import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ["button", 'form'];

	connect() {
		super.connect();
	}

	delete_user() {
		const user_id = this.buttonTarget.getAttribute('data-user-id');
		const user_uuid = this.buttonTarget.getAttribute('data-user-uuid');

		if (confirm(`Are you sure you want to delete the user with UUID ${user_uuid}?`)) {
			this.formTarget.submit();
		}
	}
}

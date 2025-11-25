import { startStimulusApp } from '@symfony/stimulus-bundle';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp();

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

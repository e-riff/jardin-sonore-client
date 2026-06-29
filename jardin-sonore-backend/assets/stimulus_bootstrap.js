import { startStimulusApp } from '@symfony/stimulus-bundle';
import UnsavedChangesController from './controllers/unsaved_changes_controller.js';

const app = startStimulusApp();
app.register('unsaved-changes', UnsavedChangesController);

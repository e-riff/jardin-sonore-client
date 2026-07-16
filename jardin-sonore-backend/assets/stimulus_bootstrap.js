import { startStimulusApp } from '@symfony/stimulus-bundle';
import AudienceMapController from './controllers/audience_map_controller.js';
import MailingAudienceController from './controllers/mailing_audience_controller.js';
import AudienceModeController from './controllers/audience_mode_controller.js';
import InternalNavController from './controllers/internal_nav_controller.js';
import RepertoireBlocksController from './controllers/repertoire_blocks_controller.js';
import UnsavedChangesController from './controllers/unsaved_changes_controller.js';

const app = startStimulusApp();
app.register('audience-map', AudienceMapController);
app.register('mailing-audience', MailingAudienceController);
app.register('audience-mode', AudienceModeController);
app.register('internal-nav', InternalNavController);
app.register('repertoire-blocks', RepertoireBlocksController);
app.register('unsaved-changes', UnsavedChangesController);

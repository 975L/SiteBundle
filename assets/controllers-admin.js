import { startStimulusApp } from '@symfony/stimulus-bundle';
import TitleConfirmController from './js/title-confirm.js';

// Back-office controllers, used only in EasyAdmin. Front-end controllers live in controllers.js
// Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('titleConfirm', TitleConfirmController);

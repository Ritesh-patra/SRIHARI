

import Alpine from 'alpinejs';
import { registerChunkedUpload } from './chunked-upload';

window.Alpine = Alpine;

registerChunkedUpload(Alpine);

Alpine.start();

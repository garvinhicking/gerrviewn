import { initializeModulesOnDOMContentLoaded } from './moduleLoader.js';

const modules = {
  tables: {
    selector: '.js-init-data-table',
    loadCallback: async () => import('./modules/table.js'),
  },
  multiselects: {
    selector: '.js-init-multiselect',
    loadCallback: async () => import('./modules/multiselect.js'),
  },
};

initializeModulesOnDOMContentLoaded(modules);

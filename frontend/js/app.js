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

document.addEventListener('DOMContentLoaded', () => {
  const themeToggle = document.getElementById('theme-toggle');
  const savedTheme = localStorage.getItem('theme');

  // Apply saved theme, if any
  if (savedTheme) {
    console.log('Restored theme: ' + savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme);
  }

  // Toggle theme on button click
  themeToggle.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    console.log('Saving theme: ' + newTheme);
  });
});

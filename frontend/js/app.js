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

  function countMainItems() {
    const visibleItems = document.querySelectorAll(".mainIssues li:not([style*='display: none'])");
    document.getElementById('displayCount').innerText = visibleItems.length;
  }

  countMainItems();

  const listItems = document.querySelectorAll('.mainIssues li');
  let isFiltered = false;
  // Filters
  const filterBlocked = document.getElementById('filter-blocked');
  filterBlocked.addEventListener('click', () => {
    isFiltered = !isFiltered;

    listItems.forEach((li) => {
      const isBlocked = li.querySelector('.merge-impossible') !== null;

      if (isFiltered) {
        li.style.display = isBlocked ? 'list-item' : 'none';
      } else {
        li.style.display = 'list-item';
      }
    });

    countMainItems();
  });

  let isMergeFiltered = false;
  // Filters
  const filterMergable = document.getElementById('filter-mergable');
  filterMergable.addEventListener('click', () => {
    isMergeFiltered = !isMergeFiltered;

    listItems.forEach((li) => {
      const isPossible = li.querySelector('.merge-possible') !== null;

      if (isMergeFiltered) {
        li.style.display = isPossible ? 'list-item' : 'none';
      } else {
        li.style.display = 'list-item';
      }
    });

    countMainItems();
  });

});

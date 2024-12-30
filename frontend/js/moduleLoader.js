export async function initializeModulesSequentially(modules) {
  for (const moduleName in modules) {
    const moduleConfig = modules[moduleName];
    const elements = document.querySelectorAll(moduleConfig.selector);

    if (elements.length > 0) {
      const { init } = await moduleConfig.loadCallback();
      for (const element of elements) {
        init(element);
      }
    }
  }
}

export function initializeModulesOnDOMContentLoaded(modules) {
  document.addEventListener('DOMContentLoaded', async () => {
    await initializeModulesSequentially(modules);
  });
}

import TomSelect from 'tom-select';

export const init = (element) => {
  return new Promise((resolve, reject) => {
    const multipleChoice = new TomSelect(element, {
      plugins: {
        remove_button: {
          title: 'X',
        }
      },
      maxOptions: null,
    });

    multipleChoice.on('initialize', function () {
      resolve();
    })
  })
}

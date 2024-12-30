import {TabulatorFull as Tabulator} from 'tabulator-tables';

export const init = (tableElement) => {

  let defaultTableOptions = {
    layout: 'fitColumns',
    responsiveLayout: 'collapse',
    rowHeader:
      {
        formatter:"responsiveCollapse",
        width:30,
        minWidth:30,
        hozAlign:"center",
        resizable:false,
        headerSort:false
      },
    layoutColumnsOnNewData: true,
    pagination: false,
    autoColumns: true,
    autoResize: true,
    headerVisibility: false,
    resizableColumnFit: false,
    resizableColumnGuide: false,
    columns: [
      {
        formatter: 'html',
        resizable: false,
        minWidth: 100,
      }
    ],
  };

  const setupTable = function (tableElement) {
    let tableDomId = tableElement.id;

    return new Tabulator('#' + tableDomId, defaultTableOptions);
  }

  return new Promise((resolve, reject) => {
    tableElement.tabulatorTable = setupTable(tableElement);

    tableElement.tabulatorTable.on('tableBuilt', () => {
      resolve();
    });
  })
}

CKEDITOR.on('dialogDefinition', function(ev) {
  var dialogName = ev.data.name;
  var dialogDefinition = ev.data.definition;

  if(dialogName === 'table') {
    var info = dialogDefinition.getContents('info');
    // Set the default table width.
    info.get('txtWidth')['default'] = '100%';

    // Modify field labels.
    info.get('txtWidth')['label'] = 'Width (px or %)';
    info.get('txtHeight')['label'] = 'Height (px or %)';

    // Remove the "Both" option for table headers.
    info.get("selHeaders")["items"].pop();
    // Remove the "First column" option for table headers.
    info.get("selHeaders")["items"].pop();
  }
});

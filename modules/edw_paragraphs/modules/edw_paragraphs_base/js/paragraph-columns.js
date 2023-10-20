(function ($, Drupal, once) {
  Drupal.behaviors.paragraph_columns = {
    attach: function (context, settings) {
      $(function () {
        let mapping = {
          1: ['100'],
          2: ['50-50', '25-75', '75-25', '33-66', '66-33'],
          3: ['33-33-33'],
          4: ['25-25-25-25']
        };

        $(once('initNumberOfColumnsField', '.field--name-field-number-of-columns select')).on('change', function () {
          let value = $(this).val();
          let columnsLayoutSelect = $(this).closest('.paragraph-type--edw-columns').find('.field--name-field-columns-layout select');
          rebuildColumnsLayoutFiled(columnsLayoutSelect, value);
        });

        $('.paragraph-type--edw-columns').each(function () {
          let columnsLayoutSelect = $(this).find('.field--name-field-columns-layout select');
          if (columnsLayoutSelect.length == 0) {
            return;
          }

          if (columnsLayoutSelect.attr('columns-processed')) {
            return;
          }

          let value = $(this).find('.field--name-field-number-of-columns select').val();
          rebuildColumnsLayoutFiled(columnsLayoutSelect, value);
          columnsLayoutSelect.attr('columns-processed', true);
        });

        function rebuildColumnsLayoutFiled(columnsLayoutSelect, numberOfColumns) {
          let allowedValues = mapping[numberOfColumns];
          if (typeof allowedValues == "undefined" || allowedValues.length == 0) {
            return;
          }

          let columnsLayoutValue = columnsLayoutSelect.val();
          let resetSelectValue = true;
          columnsLayoutSelect.find('option').each(function () {
            let optionValue = $(this).val();
            let allowedValue = allowedValues.indexOf(optionValue) != -1;

            if (allowedValue) {
              $(this).show();
              if (columnsLayoutValue === optionValue) {
                resetSelectValue = false;
              }
            } else {
              $(this).hide();
              $(this).removeAttr('selected');
            }

          });

          if (resetSelectValue) {
            let firstValue = allowedValues[0];
            columnsLayoutSelect.find('option[value="' + firstValue + '"]').attr('selected', 'selected');
          }

          columnsLayoutSelect.trigger("chosen:updated");
        }
      })
    }
  }
})(jQuery, Drupal, once);

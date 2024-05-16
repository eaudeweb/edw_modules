(function ($, Drupal, once) {
  Drupal.behaviors.paragraph_columns = {
    attach: function (context, settings) {
      $(function () {
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
          let columnsCount = parseInt(numberOfColumns);
          if (isNaN(columnsCount) || columnsCount < 1) {
            return;
          }

          let columnsLayoutValue = columnsLayoutSelect.val();
          let resetSelectValue = true;
          columnsLayoutSelect.find('option').each(function () {
            let optionValue = $(this).val();
            let allowedValue = (optionValue.split('-').length - 1) === (columnsCount - 1);

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
            let firstAllowedOption = columnsLayoutSelect.find('option:visible').first().val();
            columnsLayoutSelect.val(firstAllowedOption);
          }

          columnsLayoutSelect.trigger("chosen:updated");
        }
      });
    }
  }
})(jQuery, Drupal, once);

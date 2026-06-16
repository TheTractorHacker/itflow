// Ajax Modal Load Script

// Reusable helper: fetch modal content via AJAX and show it in a Bootstrap modal.
// options: { modalTab, onShown($modal), onHidden(), onError(error) }
window.openAjaxModal = function (modalUrl, modalSize, options) {
  options = options || {};
  modalSize = modalSize || 'md';
  const modalId = 'ajaxModal_' + Date.now();

  // Show loading spinner while fetching content
  const loadingSpinner = `
    <div id="modal-loading-spinner" class="text-center p-5">
      <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
    </div>`;
  $('.content-wrapper').append(loadingSpinner);

  // Make AJAX request
  $.ajax({
    url: modalUrl,
    method: 'GET',
    dataType: 'json',
    success: function (response) {
      $('#modal-loading-spinner').remove();

      if (response.error) {
        alert(response.error);
        if (options.onError) options.onError(response.error);
        return;
      }

      const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1">
          <div class="modal-dialog modal-${modalSize}">
            <div class="modal-content border-dark">
              ${response.content}
            </div>
          </div>
        </div>`;

      $('.content-wrapper').append(modalHtml);
      const $modal = $('#' + modalId);
      $modal.modal('show');

      // Optionally jump straight to a specific pill/tab inside the modal
      if (options.modalTab) {
        $modal.find('a[href="#' + options.modalTab + '"]').tab('show');
      }

      $modal.on('hidden.bs.modal', function () {
        $(this).remove();
        if (options.onHidden) options.onHidden();
      });

      if (options.onShown) options.onShown($modal);
    },
    error: function (xhr, status, error) {
      $('#modal-loading-spinner').remove();
      alert('Error loading modal content. Please try again.');
      console.error('Modal AJAX Error:', status, error);
      if (options.onError) options.onError(error);
    }
  });
};

$(document).on('click', '.ajax-modal', function (e) {
  e.preventDefault();

  const $trigger  = $(this);

  // Prefer data-modal-url, fallback to href — use .attr() not .data() so we
  // always read the live DOM attribute (bulk_actions.js updates it via setAttribute).
  let modalUrl = $trigger.attr('data-modal-url') || $trigger.attr('href') || '#';
  const modalSize = $trigger.data('modal-size') || 'md';
  const modalTab = $trigger.data('modal-tab');

  // If no usable URL, bail
  if (!modalUrl || modalUrl === '#') {
    console.warn('ajax-modal: No modal URL found on trigger:', this);
    return;
  }

  window.openAjaxModal(modalUrl, modalSize, { modalTab: modalTab });
});

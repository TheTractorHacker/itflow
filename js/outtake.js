// Outtake form creation + in-person signing helpers
// Used by agent/ticket.php and agent/outtake_form.php

function openOuttakeSignModal(outtakeId, ticketId, clientId) {
    var url = 'modals/ticket/outtake_sign.php?outtake_id=' + outtakeId + '&ticket_id=' + ticketId
        + (clientId ? '&client_id=' + clientId : '');

    window.openAjaxModal(url, 'lg', {
        onHidden: function () { location.reload(); }
    });
}

var outtakeCreateInFlight = false;

function createAndSignOuttake(ticketId, clientId, csrfToken) {
    // Guard against rapid repeat clicks (e.g. while waiting on the request below),
    // which previously created multiple outtake forms and stacked multiple modals.
    if (outtakeCreateInFlight) return;
    outtakeCreateInFlight = true;

    var spinner = $('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>')
        .appendTo('.content-wrapper');

    var url = 'post.php?create_outtake=' + ticketId
        + (clientId ? '&client_id=' + clientId : '')
        + '&csrf_token=' + encodeURIComponent(csrfToken);

    fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            openOuttakeSignModal(data.outtake_id, ticketId, clientId);
        } else {
            alert('Failed to create outtake form.');
        }
    })
    .catch(function () {
        alert('Failed to create outtake form.');
    })
    .finally(function () {
        outtakeCreateInFlight = false;
        spinner.remove();
    });
}

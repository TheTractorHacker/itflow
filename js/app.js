// Adds Rich Text / Markdown / HTML tabs around a TinyMCE editor whose
// underlying textarea has the "tinymce-builder" class. TinyMCE stays the
// canonical source of truth: switching tabs always syncs edits made in the
// Markdown/HTML boxes back into the editor first, then regenerates the
// target box from the editor's current content.
function initDocBuilder(editor) {
    var textarea = editor.getElement();
    if (!textarea.classList.contains('tinymce-builder') || editor.docBuilderInitialized) {
        return;
    }
    editor.docBuilderInitialized = true;

    var container = editor.getContainer();

    var tabBar = document.createElement('div');
    tabBar.className = 'btn-group btn-group-sm doc-builder-tabs mb-2';
    tabBar.setAttribute('role', 'group');
    tabBar.innerHTML =
        '<button type="button" class="btn btn-outline-secondary active" data-mode="richtext">Rich Text</button>' +
        '<button type="button" class="btn btn-outline-secondary" data-mode="markdown">Markdown</button>' +
        '<button type="button" class="btn btn-outline-secondary" data-mode="html">HTML</button>';

    var mdTextarea = document.createElement('textarea');
    mdTextarea.className = 'form-control doc-builder-source';
    mdTextarea.rows = 14;
    mdTextarea.style.display = 'none';
    mdTextarea.style.fontFamily = 'monospace';
    mdTextarea.placeholder = 'Write Markdown here...';

    var htmlTextarea = document.createElement('textarea');
    htmlTextarea.className = 'form-control doc-builder-source';
    htmlTextarea.rows = 14;
    htmlTextarea.style.display = 'none';
    htmlTextarea.style.fontFamily = 'monospace';
    htmlTextarea.placeholder = 'Raw HTML...';

    container.parentNode.insertBefore(tabBar, container);
    container.parentNode.insertBefore(mdTextarea, container.nextSibling);
    container.parentNode.insertBefore(htmlTextarea, mdTextarea.nextSibling);

    var turndownService = null;
    function getTurndown() {
        if (!turndownService) {
            turndownService = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-' });
            if (window.turndownPluginGfm) {
                turndownService.use(turndownPluginGfm.gfm);
            }
        }
        return turndownService;
    }

    var currentMode = 'richtext';

    function showMode(mode) {
        container.style.display = (mode === 'richtext') ? '' : 'none';
        mdTextarea.style.display = (mode === 'markdown') ? '' : 'none';
        htmlTextarea.style.display = (mode === 'html') ? '' : 'none';

        tabBar.querySelectorAll('button').forEach(function(btn) {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
    }

    // Pull fresh content INTO a source tab from the editor (the canonical model)
    function syncFromEditor(mode) {
        if (mode === 'markdown') {
            mdTextarea.value = getTurndown().turndown(editor.getContent());
        } else if (mode === 'html') {
            htmlTextarea.value = editor.getContent();
        }
    }

    // Push edits FROM a source tab back into the editor (the canonical model)
    function syncToEditor(mode) {
        if (mode === 'markdown') {
            editor.setContent(marked.parse(mdTextarea.value || ''));
            editor.save();
        } else if (mode === 'html') {
            editor.setContent(htmlTextarea.value || '');
            editor.save();
        }
    }

    tabBar.addEventListener('click', function(e) {
        var btn = e.target.closest('button[data-mode]');
        if (!btn || btn.dataset.mode === currentMode) {
            return;
        }

        var nextMode = btn.dataset.mode;

        if (currentMode !== 'richtext') {
            syncToEditor(currentMode);
        }
        if (nextMode !== 'richtext') {
            syncFromEditor(nextMode);
        }

        showMode(nextMode);
        currentMode = nextMode;
    });

    // Whatever tab the user was last typing in, make sure it lands in the
    // hidden textarea TinyMCE posts, even if they never switch back to Rich Text.
    var form = textarea.form;
    if (form) {
        form.addEventListener('submit', function() {
            if (currentMode !== 'richtext') {
                syncToEditor(currentMode);
            }
        });
    }
}

$(document).ready(function() {
    // Prevents resubmit on forms
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Slide alert up after 4 secs
    $("#alert").fadeTo(5000, 500).slideUp(500, function() {
        $("#alert").slideUp(500);
    });

    // Initialize Select2 Elements
    $('.select2').select2({
        theme: 'bootstrap4',
    });

    // Initialize TinyMCE
    tinymce.init({
        selector: '.tinymce-simple',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'alignment', items: ['alignleft', 'aligncenter', 'alignright', 'alignjustify'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'table', items: ['table'] },
            { name: 'extra', items: ['code', 'fullscreen'] }
        ],
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'bold italic styles'
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize',
        setup: function (editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });
        },
        license_key: 'gpl'
    });

    // Initialize TinyMCE with AI
    tinymce.init({
        selector: '.tinymce',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'alignment', items: ['alignleft', 'aligncenter', 'alignright', 'alignjustify'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'table', items: ['table'] },
            { name: 'media', items: ['image'] },
            { name: 'extra', items: ['code', 'fullscreen'] },
            { name: 'ai', items: ['reword', 'undo', 'redo'] }
        ],
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'bold italic styles'
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize',
        images_upload_url: '/agent/kb_article_upload.php',
        images_upload_credentials: true,
        license_key: 'gpl',
        setup: function(editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }

                initDocBuilder(editor);
            });

            var rewordButtonApi;

            editor.ui.registry.addButton('reword', {
                icon: 'ai',
                tooltip: 'Reword Text',
                onAction: function() {
                    var content = editor.getContent();

                    // Disable the Reword button
                    rewordButtonApi.setEnabled(false);

                    // Show the progress indicator
                    editor.setProgressState(true);

                    fetch('ajax.php?ai_reword', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ text: content }),
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            editor.undoManager.transact(function() {
                                editor.setContent(data.rewordedText || 'Error: Could not reword the text.');
                            });

                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);

                            editor.notificationManager.open({
                                text: 'Text reworded successfully!',
                                type: 'success',
                                timeout: 3000
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);
                            editor.notificationManager.open({
                                text: 'An error occurred while rewording the text.',
                                type: 'error',
                                timeout: 5000
                            });
                        });
                },
                onSetup: function(buttonApi) {
                    rewordButtonApi = buttonApi;
                    return function() {};
                }
            });
        }
    });

    // Initialize TinyMCE AI for Tickets
    tinymce.init({
        selector: '.tinymceTicket',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 200,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'ai', items: ['reword', 'undo', 'redo'] },
            { name: 'custom', items: ['redactButton'] },
            { name: 'code', items: ['code'] },
        ],
        mobile: {
            menubar: false,
            toolbar: [
                { name: 'styles', items: ['styles'] },
                { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
                { name: 'link', items: ['link'] },
                { name: 'lists', items: ['bullist', 'numlist'] },
                { name: 'indentation', items: ['outdent', 'indent'] },
                { name: 'ai', items: ['reword', 'undo', 'redo'] },
                { name: 'custom', items: ['redactButton'] },
                { name: 'code', items: ['code'] },
            ],
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize code',
        license_key: 'gpl',
        setup: function(editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }

            });

            var rewordButtonApi;

            editor.ui.registry.addButton('reword', {
                icon: 'ai',
                tooltip: 'Reword Text',
                onAction: function() {
                    var content = editor.getContent();
                    rewordButtonApi.setEnabled(false);
                    editor.setProgressState(true);

                    fetch('ajax.php?ai_reword', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: content }),
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            editor.undoManager.transact(function() {
                                editor.setContent(data.rewordedText || 'Error: Could not reword the text.');
                            });
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);
                            editor.notificationManager.open({
                                text: 'Text reworded successfully!',
                                type: 'success',
                                timeout: 3000
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);
                            editor.notificationManager.open({
                                text: 'An error occurred while rewording the text.',
                                type: 'error',
                                timeout: 5000
                            });
                        });
                },
                onSetup: function(buttonApi) {
                    rewordButtonApi = buttonApi;
                    return function() {};
                }
            });

            editor.ui.registry.addButton('redactButton', {
                icon: 'permanent-pen',
                tooltip: 'Redact Text',
                onAction: function() {
                    var selectedText = editor.selection.getContent({ format: 'text' });
                    if (selectedText) {
                        var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
                        editor.selection.setContent(newContent);
                    } else {
                        alert('Please select a word to redact');
                    }
                }
            });
        }
    });

    // Initialize TinyMCE Redact-only
    tinymce.init({
        selector: '.tinymceRedact',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: 'redactButton',
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'redactButton'
        },
        convert_urls: false,
        plugins: 'link image lists table code fullscreen autoresize',
        license_key: 'gpl',
        setup: function(editor) {

            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });

            editor.on('keydown', function(e) {
                e.preventDefault();
            });

            editor.ui.registry.addButton('redactButton', {
                icon: 'permanent-pen',
                tooltip: 'Redact',
                text: 'REDACT',
                onAction: function() {
                    var selectedText = editor.selection.getContent({ format: 'text' });
                    if (selectedText) {
                        var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
                        editor.selection.setContent(newContent);
                    } else {
                        alert('Please select a word to redact');
                    }
                }
            });
        }
    });

    // DateTime
    $('.datetimepicker').datetimepicker();

    // Data Input Mask
    $('[data-mask]').inputmask();

    // ClipboardJS fix for Bootstrap modals
    $.fn.modal.Constructor.prototype._enforceFocus = function() {};

    // Tooltip
    $('button').tooltip({
        trigger: 'click',
        placement: 'bottom'
    });

    function setTooltip(btn, message) {
        $(btn).tooltip('hide')
            .attr('data-original-title', message)
            .tooltip('show');
    }

    function hideTooltip(btn) {
        setTimeout(function() {
            $(btn).tooltip('hide');
        }, 1000);
    }

    // Clipboard
    var clipboard = new ClipboardJS('.clipboardjs');

    clipboard.on('success', function(e) {
        setTooltip(e.trigger, 'Copied!');
        hideTooltip(e.trigger);
    });

    clipboard.on('error', function(e) {
        setTooltip(e.trigger, 'Failed!');
        hideTooltip(e.trigger);
    });

    // Enable Popovers
    $(function() {
        $('[data-toggle="popover"]').popover();
    });

    // Data Tables
    new DataTable('.dataTables');

    // Dropdowns inside a .table-responsive get clipped by its scroll container, and
    // Popper's flip only swaps left/right for dropleft/dropright menus - it won't flip
    // to open upward, so a menu near the bottom of the page can render off-screen and
    // force a page scroll to reach it. Reparent the menu to <body> while open and
    // position it manually (viewport-aware, flips up if there's no room below).
    $(document).on('show.bs.dropdown', '.table-responsive .dropdown', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.children('.dropdown-menu').first();
        var $toggle = $dropdown.find('[data-toggle="dropdown"]').first();

        if (!$menu.length || !$toggle.length) {
            return;
        }

        var $placeholder = $('<span style="display:none"></span>').insertAfter($menu);
        $dropdown.data('trf-placeholder', $placeholder);
        $dropdown.data('trf-menu', $menu);
        // Reverse pointer so click handlers on items inside the menu (which now sees
        // $item.closest('.dropdown') fail, since the menu no longer lives under the
        // original .dropdown while open) can still find their way back to it.
        $menu.data('trf-dropdown', $dropdown);

        $menu.appendTo('body').css({
            position: 'fixed',
            margin: 0,
            maxHeight: '70vh',
            overflowY: 'auto',
            zIndex: 1071
        });
    });

    $(document).on('shown.bs.dropdown', '.table-responsive .dropdown', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.data('trf-menu');
        var $toggle = $dropdown.find('[data-toggle="dropdown"]').first();

        if (!$menu || !$menu.length || !$toggle.length) {
            return;
        }

        var rect = $toggle[0].getBoundingClientRect();
        var menuHeight = $menu.outerHeight();
        var menuWidth = $menu.outerWidth();

        var top = rect.bottom;
        if (top + menuHeight > $(window).height()) {
            top = Math.max(rect.top - menuHeight, 8);
        }

        var left = rect.right - menuWidth;
        if (left < 8) {
            left = Math.min(rect.left, $(window).width() - menuWidth - 8);
        }

        $menu.css({ top: top + 'px', left: left + 'px' });
    });

    $(document).on('hidden.bs.dropdown', '.table-responsive .dropdown', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.data('trf-menu');
        var $placeholder = $dropdown.data('trf-placeholder');

        if ($menu && $menu.length && $placeholder && $placeholder.length) {
            $menu.css({ position: '', margin: '', maxHeight: '', overflowY: '', zIndex: '', top: '', left: '' });
            $placeholder.replaceWith($menu);
        }

        $dropdown.removeData('trf-menu');
        $dropdown.removeData('trf-placeholder');
    });
});

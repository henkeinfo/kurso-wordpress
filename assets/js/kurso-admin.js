jQuery(function ($) {

    // Auto-generate slug from name (new queries only)
    $('#q_name').on('input', function () {
        var slug = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        var slugField = $('#q_slug');
        if (!slugField.prop('readonly')) {
            slugField.val(slug);
        }
    });

    // Helper: get current value from a CodeMirror instance or raw textarea
    function getEditorValue(editor, fallbackId) {
        if (editor) return editor.getValue();
        var el = document.getElementById(fallbackId);
        return el ? el.value : '';
    }

    // Track CodeMirror editor instances (populated below if CodeMirror loads)
    var gqlEditor = null;
    var varEditor = null;

    // Helper: evaluate variables via REST
    function evaluateVariables(callback) {
        if (typeof kursoAdmin === 'undefined') {
            callback('kursoAdmin not available', null);
            return;
        }
        var json = getEditorValue(varEditor, 'q_variables');
        $.ajax({
            url: kursoAdmin.restUrl + 'evaluate-variables',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', kursoAdmin.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ variables: json }),
            success: function (data) { callback(null, data.result); },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : xhr.statusText;
                callback(msg, null);
            }
        });
    }

    // Evaluate button — works regardless of CodeMirror
    $('#kurso-eval-vars').on('click', function () {
        var $btn     = $(this);
        var $spinner = $('#kurso-eval-spinner');
        var $result  = $('#kurso-var-result');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        evaluateVariables(function (err, result) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (err) {
                $result
                    .removeClass('kurso-var-result--ok')
                    .addClass('kurso-var-result--error')
                    .text('Error: ' + err)
                    .show();
            } else {
                $result
                    .removeClass('kurso-var-result--error')
                    .addClass('kurso-var-result--ok')
                    .text(JSON.stringify(result, null, 2))
                    .show();
            }
        });
    });

    // Open in GraphiQL button — works regardless of CodeMirror
    $('#kurso-open-graphiql').on('click', function () {
        if (typeof kursoAdmin === 'undefined' || !kursoAdmin.graphqlUrl) {
            alert('Please configure the GraphQL URL in Connection settings first.');
            return;
        }

        var $btn     = $(this);
        var $spinner = $('#kurso-graphiql-spinner');
        var query    = getEditorValue(gqlEditor, 'q_graphql');
        var varJson  = getEditorValue(varEditor, 'q_variables').trim();

        function buildUrl(variables) {
            var url = kursoAdmin.graphqlUrl
                + '?query=' + encodeURIComponent(query);
            if (variables && Object.keys(variables).length > 0) {
                url += '&variables=' + encodeURIComponent(JSON.stringify(variables, null, 2));
            }
            return url;
        }

        // No Twig in variables — open directly without async
        if (!varJson || !varJson.includes('{{')) {
            try {
                var parsed = varJson ? JSON.parse(varJson) : {};
                window.open(buildUrl(parsed), '_blank');
            } catch (e) {
                window.open(buildUrl({}), '_blank');
            }
            return;
        }

        // Variables contain Twig — open blank tab NOW (avoids popup blocker),
        // then navigate it after server-side evaluation.
        var tab = window.open('about:blank', '_blank');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        evaluateVariables(function (err, result) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            if (tab) {
                tab.location.href = buildUrl(err ? {} : result);
            }
        });
    });

    // -------------------------------------------------------------------------
    // CodeMirror editors — only initialized if CodeMirror is available.
    // Use window.kursoCM (saved before other plugins can overwrite window.CodeMirror).
    // -------------------------------------------------------------------------
    var CM = window.kursoCM || (window.wp && window.wp.CodeMirror) || window.CodeMirror;
    if (!CM || typeof CM.fromTextArea !== 'function') return;

    var cmOptions = {
        lineNumbers: true,
        lineWrapping: false,
        indentUnit: 2,
        tabSize: 2,
        indentWithTabs: false,
        extraKeys: { Tab: function (cm) { cm.replaceSelection('  ', 'end'); } }
    };

    // GraphQL query field
    var gqlArea = document.getElementById('q_graphql');
    if (gqlArea) {
        gqlEditor = CM.fromTextArea(gqlArea, Object.assign({}, cmOptions, {
            mode: 'graphql',
            theme: 'default'
        }));
    }

    // Variables field (JSON + Twig)
    var varArea = document.getElementById('q_variables');
    if (varArea) {
        varEditor = CM.fromTextArea(varArea, Object.assign({}, cmOptions, {
            mode: { name: 'javascript', json: true },
            theme: 'default'
        }));
    }

    // Twig template field (HTML + Twig)
    var tplArea = document.getElementById('q_template');
    if (tplArea) {
        var twigMode = (typeof CM.multiplexingMode !== 'undefined')
            ? { name: 'twig', base: 'htmlmixed' }
            : 'twig';
        CM.fromTextArea(tplArea, Object.assign({}, cmOptions, {
            mode: twigMode,
            theme: 'default',
            lineWrapping: true
        }));
    }
});

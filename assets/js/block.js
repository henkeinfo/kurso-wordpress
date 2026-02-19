/* global kursoBlockData, wp */
(function (blocks, element, editor, components, apiFetch) {
    var el = element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = editor ? editor.InspectorControls : null;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextareaControl = components.TextareaControl;
    var TextControl = components.TextControl;
    var Button = components.Button;
    var Spinner = components.Spinner;

    var queries = (kursoBlockData && kursoBlockData.queries) ? kursoBlockData.queries : [];
    var queryOptions = [{ label: '– Query wählen –', value: '' }].concat(
        queries.map(function (q) { return { label: q.label, value: q.slug }; })
    );

    blocks.registerBlockType('kurso/anzeige', {
        title: 'KURSO Anzeige',
        icon: 'calendar',
        category: 'embed',
        attributes: {
            query:    { type: 'string', default: '' },
            template: { type: 'string', default: '' },
            cssClass: { type: 'string', default: '' },
        },

        edit: function (props) {
            var attrs = props.attributes;
            var setAttr = props.setAttributes;
            var state = element.useState({ html: '', loading: false, rawData: null, showRaw: false });
            var html = state[0].html;
            var loading = state[0].loading;
            var rawData = state[0].rawData;
            var showRaw = state[0].showRaw;
            var setState = state[1];

            function loadPreview() {
                if (!attrs.query) return;
                setState(function (s) { return Object.assign({}, s, { loading: true }); });
                apiFetch({
                    path: '/kurso/v1/preview',
                    method: 'POST',
                    data: { query: attrs.query, template: attrs.template },
                }).then(function (res) {
                    setState(function (s) { return Object.assign({}, s, { html: res.html || '', loading: false }); });
                }).catch(function () {
                    setState(function (s) { return Object.assign({}, s, { html: '<em>Vorschau-Fehler</em>', loading: false }); });
                });
            }

            function loadRawData() {
                if (!attrs.query) return;
                apiFetch({ path: '/kurso/v1/rawdata/' + attrs.query })
                    .then(function (res) {
                        setState(function (s) { return Object.assign({}, s, { rawData: res.data, showRaw: true }); });
                    });
            }

            // Vorschau laden wenn Query sich ändert
            element.useEffect(function () { loadPreview(); }, [attrs.query, attrs.template]);

            var inspector = InspectorControls ? el(InspectorControls, null,
                el(PanelBody, { title: 'KURSO Einstellungen', initialOpen: true },
                    el(SelectControl, {
                        label: 'Query',
                        value: attrs.query,
                        options: queryOptions,
                        onChange: function (v) { setAttr({ query: v }); },
                    }),
                    el(TextareaControl, {
                        label: 'Twig-Template (optional)',
                        value: attrs.template,
                        rows: 10,
                        onChange: function (v) { setAttr({ template: v }); },
                        help: 'Leer lassen um das am Query hinterlegte Template zu verwenden.',
                    }),
                    el(TextControl, {
                        label: 'CSS-Klasse',
                        value: attrs.cssClass,
                        onChange: function (v) { setAttr({ cssClass: v }); },
                    }),
                    el(Button, { isSecondary: true, onClick: loadPreview }, 'Vorschau aktualisieren'),
                    el('br'),
                    el(Button, { isLink: true, onClick: showRaw ? function () { setState(function (s) { return Object.assign({}, s, { showRaw: false }); }); } : loadRawData },
                        showRaw ? 'Rohdaten ausblenden' : 'Rohdaten anzeigen')
                )
            ) : null;

            var preview;
            if (!attrs.query) {
                preview = el('div', { style: { padding: '20px', background: '#f0f6fc', border: '2px dashed #2271b1', borderRadius: '6px', textAlign: 'center' } },
                    el('p', null, '⚙️ KURSO Anzeige'),
                    el('p', { style: { color: '#64748b', fontSize: '13px' } }, 'Bitte ein Query in der Seitenleiste auswählen.')
                );
            } else if (loading) {
                preview = el('div', { style: { padding: '20px', textAlign: 'center' } }, el(Spinner), ' Lade Vorschau…');
            } else if (showRaw && rawData) {
                preview = el('pre', {
                    style: { background: '#1e293b', color: '#e2e8f0', padding: '16px', borderRadius: '6px', overflow: 'auto', fontSize: '12px', maxHeight: '400px' }
                }, JSON.stringify(rawData, null, 2));
            } else {
                preview = el('div', { dangerouslySetInnerHTML: { __html: html || '<em style="color:#94a3b8">Keine Vorschau verfügbar.</em>' } });
            }

            return el('div', null, inspector, preview);
        },

        save: function () {
            return null; // Server-side rendering
        },
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor || window.wp.editor,
    window.wp.components,
    window.wp.apiFetch
));

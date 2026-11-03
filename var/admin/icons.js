/*
 * Portix 3 — sous-ensemble local d'icônes Lucide / Feather.
 * SVG décoratifs : aria-hidden="true".
 */
(() => {
    'use strict';
    const NS = 'http://www.w3.org/2000/svg';

    const icons = {
        'images': [
            ['path', { d:'M4 7H3a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-1' }],
            ['rect', { x:'6', y:'2', width:'16', height:'16', rx:'2' }],
            ['circle', { cx:'11', cy:'7', r:'1' }],
            ['path', { d:'m6 14 4-4 4 4 3-3 5 5' }]
        ],
        'activity': [
            ['path', { d:'M2 12h4l3-9 6 18 3-9h4' }]
        ],
        'layout-dashboard': [
            ['rect', { x:'3', y:'3', width:'7', height:'9', rx:'1' }],
            ['rect', { x:'14', y:'3', width:'7', height:'5', rx:'1' }],
            ['rect', { x:'14', y:'12', width:'7', height:'9', rx:'1' }],
            ['rect', { x:'3', y:'16', width:'7', height:'5', rx:'1' }]
        ],
        'newspaper': [
            ['path', { d:'M4 22h16a2 2 0 0 0 2-2V4H6v16a2 2 0 0 1-2 2Z' }],
            ['path', { d:'M2 6v14a2 2 0 0 0 2 2' }],
            ['path', { d:'M10 8h8' }], ['path', { d:'M10 12h8' }],
            ['path', { d:'M10 16h8' }], ['path', { d:'M6 8h.01' }],
            ['path', { d:'M6 12h.01' }], ['path', { d:'M6 16h.01' }]
        ],
        'settings': [
            ['path', { d:'M4 21v-7' }], ['path', { d:'M4 10V3' }],
            ['path', { d:'M12 21v-9' }], ['path', { d:'M12 8V3' }],
            ['path', { d:'M20 21v-5' }], ['path', { d:'M20 12V3' }],
            ['path', { d:'M1 14h6' }], ['path', { d:'M9 8h6' }],
            ['path', { d:'M17 16h6' }]
        ],
        'users': [
            ['path', { d:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2' }],
            ['circle', { cx:'9', cy:'7', r:'4' }],
            ['path', { d:'M22 21v-2a4 4 0 0 0-3-3.87' }],
            ['path', { d:'M16 3.13a4 4 0 0 1 0 7.75' }]
        ],
        'boxes': [
            ['path', { d:'M12 2 2 7l10 5 10-5-10-5Z' }],
            ['path', { d:'m2 17 10 5 10-5' }],
            ['path', { d:'m2 12 10 5 10-5' }]
        ],
        'dollar-sign': [
            ['line', { x1:'12', y1:'2', x2:'12', y2:'22' }],
            ['path', { d:'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6' }]
        ],
        'triangle-alert': [
            ['path', { d:'M10.3 3.9 1.8 18.2A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z' }],
            ['line', { x1:'12', y1:'9', x2:'12', y2:'13' }],
            ['line', { x1:'12', y1:'17', x2:'12.01', y2:'17' }]
        ],
        'external-link': [
            ['path', { d:'M15 3h6v6' }],
            ['path', { d:'M10 14 21 3' }],
            ['path', { d:'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }]
        ],
        'log-out': [
            ['path', { d:'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4' }],
            ['polyline', { points:'16 17 21 12 16 7' }],
            ['line', { x1:'21', y1:'12', x2:'9', y2:'12' }]
        ],
        'file-text': [
            ['path', { d:'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z' }],
            ['polyline', { points:'14 2 14 8 20 8' }],
            ['line', { x1:'16', y1:'13', x2:'8', y2:'13' }],
            ['line', { x1:'16', y1:'17', x2:'8', y2:'17' }],
            ['line', { x1:'10', y1:'9', x2:'8', y2:'9' }]
        ],
        'menu': [
            ['line', { x1:'4', y1:'6', x2:'20', y2:'6' }],
            ['line', { x1:'4', y1:'12', x2:'20', y2:'12' }],
            ['line', { x1:'4', y1:'18', x2:'20', y2:'18' }]
        ],
        'download': [
            ['path', { d:'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4' }],
            ['polyline', { points:'7 10 12 15 17 10' }],
            ['line', { x1:'12', y1:'15', x2:'12', y2:'3' }]
        ],
        'mail': [
            ['rect', { x:'3', y:'5', width:'18', height:'14', rx:'2' }],
            ['polyline', { points:'3 7 12 13 21 7' }]
        ],
        'chart-bar': [
            ['line', { x1:'18', y1:'20', x2:'18', y2:'10' }],
            ['line', { x1:'12', y1:'20', x2:'12', y2:'4' }],
            ['line', { x1:'6', y1:'20', x2:'6', y2:'14' }]
        ],
        'plus': [
            ['line', { x1:'12', y1:'5', x2:'12', y2:'19' }],
            ['line', { x1:'5', y1:'12', x2:'19', y2:'12' }]
        ],
        'pencil': [
            ['path', { d:'M12 20h9' }],
            ['path', { d:'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z' }]
        ],
        'trash-2': [
            ['polyline', { points:'3 6 5 6 21 6' }],
            ['path', { d:'M19 6l-1 14H6L5 6' }],
            ['path', { d:'M10 11v6' }], ['path', { d:'M14 11v6' }],
            ['path', { d:'M9 6V4h6v2' }]
        ],
        'circle-check': [
            ['path', { d:'M22 11.08V12a10 10 0 1 1-5.93-9.14' }],
            ['polyline', { points:'22 4 12 14.01 9 11.01' }]
        ],
        'circle-x': [
            ['circle', { cx:'12', cy:'12', r:'10' }],
            ['line', { x1:'15', y1:'9', x2:'9', y2:'15' }],
            ['line', { x1:'9', y1:'9', x2:'15', y2:'15' }]
        ],
        'search': [
            ['circle', { cx:'11', cy:'11', r:'8' }],
            ['line', { x1:'21', y1:'21', x2:'16.65', y2:'16.65' }]
        ],
        'panel-bottom': [
            ['rect', { x:'3', y:'3', width:'18', height:'18', rx:'2' }],
            ['line', { x1:'3', y1:'15', x2:'21', y2:'15' }]
        ],
        'key-round': [
            ['circle', { cx:'7.5', cy:'15.5', r:'5.5' }],
            ['path', { d:'m21 2-9.6 9.6' }],
            ['path', { d:'m15.5 7.5 3 3L22 7l-3-3' }]
        ],
        'circle-user': [
            ['circle', { cx:'12', cy:'12', r:'10' }],
            ['circle', { cx:'12', cy:'9', r:'3' }],
            ['path', { d:'M6.2 18.4c1.2-2.3 3.1-3.4 5.8-3.4s4.6 1.1 5.8 3.4' }]
        ],
        'arrow-left': [
            ['line', { x1:'19', y1:'12', x2:'5', y2:'12' }],
            ['polyline', { points:'12 19 5 12 12 5' }]
        ]
    };

    const makeIcon = (name) => {
        if (!icons[name]) return null;
        const svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.classList.add('portix-admin-icon');

        for (const [tag, attrs] of icons[name]) {
            const node = document.createElementNS(NS, tag);
            for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);
            svg.appendChild(node);
        }
        return svg;
    };

    const routeIcon = (href) => {
        const value = (href || '').toLowerCase();
        if (value === 'admin.html' || value.endsWith('/admin.html')) return 'layout-dashboard';
        if (value.includes('admin-media')) return 'images';
        if (value.includes('admin-diagnostics')) return 'activity';
        if (value.includes('admin-news-new')) return 'plus';
        if (value.includes('admin-news')) return 'newspaper';
        if (value.includes('admin-config')) return 'settings';
        if (value.includes('admin-users')) return 'users';
        if (value.includes('admin-modmgr')) return 'boxes';
        if (value.includes('admin-articles')) return 'file-text';
        if (value.includes('admin-menu')) return 'menu';
        if (value.includes('admin-download') || value.includes('admin-dwl')) return 'download';
        if (value.includes('admin-contact')) return 'mail';
        if (value.includes('admin-insights')) return 'chart-bar';
        if (value.includes('admin-safety')) return 'triangle-alert';
        if (value.includes('admin-footer')) return 'panel-bottom';
        if (value.includes('admin-search')) return 'search';
        if (value.includes('admin-usr')) return 'key-round';
        if (value.includes('-new')) return 'plus';
        if (value.includes('-edit')) return 'pencil';
        return null;
    };

    const prependIcon = (element, name) => {
        if (!element || !name || element.querySelector(':scope > .portix-admin-icon')) return;
        const icon = makeIcon(name);
        if (icon) element.prepend(icon);
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.admin-content [data-admin-icon]').forEach((element) => {
            prependIcon(element, element.getAttribute('data-admin-icon'));
        });
        document.querySelectorAll('.admin-content a, .topbar-actions a, .admin-sidebar a').forEach((link) => {
            let name = link.getAttribute('data-admin-icon') || routeIcon(link.getAttribute('href'));
            if (!name && link.closest('.topbar-actions')) name = 'external-link';
            prependIcon(link, name);
        });

        document.querySelectorAll('.admin-content button, .topbar-actions button, .admin-sidebar button').forEach((button) => {
            const form = button.closest('form');
            const action = (form?.getAttribute('action') || '').toLowerCase();
            let name = button.getAttribute('data-admin-icon') || null;

            if (!name && button.classList.contains('button-danger') || action.includes('delete') || action.includes('remove')) name = 'trash-2';
            else if (!name && button.classList.contains('button-enable')) name = 'circle-check';
            else if (!name && button.classList.contains('button-disable')) name = 'circle-x';
            else if (!name && action.includes('disconnect')) name = 'log-out';
            else if (!name && button.classList.contains('button-dashboard')) name = 'layout-dashboard';

            prependIcon(button, name);
        });

        document.querySelectorAll('.admin-content a').forEach((link) => {
            if (link.querySelector(':scope > .portix-admin-icon')) return;
            const text = (link.textContent || '').trim().toLowerCase();
            if (/^(retour|back)\b/.test(text)) prependIcon(link, 'arrow-left');
        });
    });
})();

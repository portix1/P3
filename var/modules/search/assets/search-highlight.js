(function () {
    'use strict';

    function normalise(value) {
        return String(value || '')
            .replace(/\s+/gu, ' ')
            .trim()
            .toLocaleLowerCase();
    }

    function textNodes(element) {
        var nodes = [];
        var walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function (node) {
                    if (!node.nodeValue || !node.nodeValue.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    var parent = node.parentElement;
                    if (!parent || parent.closest('script, style, noscript, mark.p3-search-hit')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );
        var node;
        while ((node = walker.nextNode())) {
            nodes.push(node);
        }
        return nodes;
    }

    function buildNormalisedMap(element) {
        var content = '';
        var map = [];
        var lastWasSpace = false;

        textNodes(element).forEach(function (node) {
            var value = node.nodeValue || '';
            for (var i = 0; i < value.length; i += 1) {
                var character = value.charAt(i);
                if (/\s/u.test(character)) {
                    if (!lastWasSpace && content !== '') {
                        content += ' ';
                        map.push({ node: node, offset: i });
                    }
                    lastWasSpace = true;
                    continue;
                }
                content += character;
                map.push({ node: node, offset: i });
                lastWasSpace = false;
            }
        });

        return { text: content.toLocaleLowerCase(), map: map };
    }

    function candidateBlocks(root) {
        var selector = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'li', 'blockquote', 'figcaption',
            'td', 'th'
        ].join(',');
        var blocks = Array.from(root.querySelectorAll(selector));
        if (blocks.length === 0) {
            blocks.push(root);
        }
        return blocks;
    }

    function targetSegments(block, phrase) {
        var built = buildNormalisedMap(block);
        var needle = normalise(phrase);
        if (!needle) { return []; }

        var start = built.text.indexOf(needle);
        if (start < 0) { return []; }

        var end = start + needle.length - 1;
        if (!built.map[start] || !built.map[end]) { return []; }

        var segments = [];
        var current = null;

        for (var i = start; i <= end; i += 1) {
            var point = built.map[i];
            if (!point) { continue; }

            if (!current || current.node !== point.node) {
                current = {
                    node: point.node,
                    start: point.offset,
                    end: point.offset + 1
                };
                segments.push(current);
            } else {
                current.end = Math.max(current.end, point.offset + 1);
            }
        }

        return segments;
    }

    function wrapSegment(segment) {
        var node = segment.node;
        if (!node || !node.parentNode || segment.end <= segment.start) {
            return null;
        }

        var tail = null;
        if (segment.end < node.nodeValue.length) {
            tail = node.splitText(segment.end);
        }
        var middle = segment.start > 0
            ? node.splitText(segment.start)
            : node;

        var mark = document.createElement('mark');
        mark.className = 'p3-search-hit';
        middle.parentNode.insertBefore(mark, middle);
        mark.appendChild(middle);
        return mark;
    }

    function cleanUrl() {
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('p3hl');
            var next = url.pathname
                + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '')
                + url.hash;
            window.history.replaceState(window.history.state, '', next);
        } catch (error) {
            // Le surlignage fonctionne même si l'URL ne peut pas être nettoyée.
        }
    }

    function run() {
        var params = new URLSearchParams(window.location.search);
        var phrase = params.get('p3hl');
        if (!phrase) { return; }

        var root = document.querySelector('#main-content')
            || document.querySelector('main')
            || document.body;

        var blocks = candidateBlocks(root);
        var marks = [];

        for (var i = 0; i < blocks.length; i += 1) {
            var segments = targetSegments(blocks[i], phrase);
            if (segments.length === 0) { continue; }

            // Modifier les derniers nœuds d'abord préserve les offsets des précédents.
            for (var j = segments.length - 1; j >= 0; j -= 1) {
                var mark = wrapSegment(segments[j]);
                if (mark) {
                    marks.unshift(mark);
                }
            }
            break;
        }

        if (marks.length > 0) {
            window.setTimeout(function () {
                marks[0].scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                        ? 'auto'
                        : 'smooth',
                    block: 'center'
                });
            }, 0);
        }

        cleanUrl();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
}());

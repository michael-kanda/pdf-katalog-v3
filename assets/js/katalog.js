/**
 * PDF Katalog – Frontend Application v3.0
 * ========================================
 * Multi-instance support: each [pdf_katalog] shortcode
 * creates its own scoped viewer instance.
 */

(function () {
    'use strict';

    function initInstance(DATA) {
        var TOC     = DATA.toc || [];
        var PDF_URL = DATA.pdfUrl || '';
        var WORKER  = DATA.workerSrc || '';
        var ROOT_ID = DATA.instanceId || '';

        if (!PDF_URL || !ROOT_ID) return;

        var pdfDoc      = null;
        var currentPage = 1;
        var totalPages  = 0;
        var scale       = 1.0;
        var rendering   = false;
        var pendingRender = null;
        var pageTexts   = {};
        var textExtracted = false;
        var doubleMode  = false;
        var flipping    = false;

        /* Scoped DOM helpers */
        var elRoot = document.getElementById(ROOT_ID);
        if (!elRoot) return;

        var $ = function (s) { return elRoot.querySelector(s); };
        var $$ = function (s) { return Array.prototype.slice.call(elRoot.querySelectorAll(s)); };

        var elSidebar    = $('.pdk-sidebar');
        var elToc        = $('.pdk-toc');
        var elCanvasWrap = $('.pdk-canvas-wrap');
        var elBook       = $('.pdk-book');
        var elCanvasL    = $('.pdk-canvas-left');
        var elCanvasR    = $('.pdk-canvas-right');
        var elLoading    = $('.pdk-loading');
        var elSearch     = $('.pdk-search');
        var elSearchClear = $('.pdk-search-clear');
        var elSearchInfo = $('.pdk-search-results-info');
        var elPageInput  = $('.pdk-page-input');
        var elPageTotal  = $('.pdk-page-total');
        var elZoomLevel  = $('.pdk-zoom-level');
        var ctxL         = elCanvasL.getContext('2d');
        var ctxR         = elCanvasR.getContext('2d');

        /* ═══════════════════════════════════════
           1. Build TOC Accordion
           ═══════════════════════════════════════ */

        function buildTOC() {
            var html = '';
            TOC.forEach(function (ch, ci) {
                var fp = ch.items && ch.items.length ? ch.items[0].page : 1;
                html += '<div class="pdk-chapter" data-chapter="' + ci + '">';
                html +=   '<button class="pdk-chapter-btn" data-first-page="' + fp + '">';
                html +=     '<span class="pdk-chapter-label">' + escHTML(ch.chapter) + '</span>';
                html +=     '<svg class="pdk-chapter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 6 15 12 9 18"/></svg>';
                html +=   '</button>';
                html +=   '<div class="pdk-chapter-items">';
                (ch.items || []).forEach(function (item) {
                    html += '<a class="pdk-item" data-page="' + item.page + '" href="#">';
                    html +=   '<span class="pdk-item-label">' + escHTML(item.title) + '</span>';
                    html +=   '<span class="pdk-item-page">S. ' + item.page + '</span>';
                    html += '</a>';
                });
                html += '</div></div>';
            });
            elToc.innerHTML = html;

            $$('.pdk-chapter-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var ch = btn.closest('.pdk-chapter');
                    var wasOpen = ch.classList.contains('pdk-open');
                    $$('.pdk-chapter').forEach(function (c) { c.classList.remove('pdk-open'); });
                    if (!wasOpen) ch.classList.add('pdk-open');
                });
            });

            $$('.pdk-item').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var p = parseInt(a.dataset.page, 10);
                    if (p) goToPage(p);
                });
            });
        }

        /* ═══════════════════════════════════════
           2. PDF.js – Load & Render
           ═══════════════════════════════════════ */

        function initPDF() {
            pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER;
            pdfjsLib.getDocument(PDF_URL).promise.then(function (pdf) {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                elPageTotal.textContent = totalPages;
                elPageInput.max = totalPages;
                elLoading.classList.add('pdk-loaded');
                renderCurrent();
                extractAllText();
            }).catch(function (err) {
                elLoading.innerHTML = '<span style="color:var(--pdk-accent);">Fehler beim Laden.</span><br><small>' + escHTML(String(err)) + '</small>';
            });
        }

        function renderPageToCanvas(pageNum, canvas, ctx, cb) {
            pdfDoc.getPage(pageNum).then(function (page) {
                var vp = page.getViewport({ scale: scale });
                var dpr = window.devicePixelRatio || 1;
                canvas.width  = vp.width  * dpr;
                canvas.height = vp.height * dpr;
                canvas.style.width  = vp.width  + 'px';
                canvas.style.height = vp.height + 'px';
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
                    if (cb) cb();
                });
            });
        }

        function renderCurrent() {
            if (rendering) { pendingRender = true; return; }
            rendering = true;

            var leftPage  = currentPage;
            var rightPage = doubleMode ? currentPage + 1 : 0;
            if (doubleMode && rightPage > totalPages) rightPage = 0;

            var done = 0;
            var needed = rightPage > 0 ? 2 : 1;

            function checkDone() {
                done++;
                if (done >= needed) {
                    rendering = false;
                    if (pendingRender) { pendingRender = false; renderCurrent(); }
                }
            }

            renderPageToCanvas(leftPage, elCanvasL, ctxL, checkDone);

            if (rightPage > 0) {
                elCanvasR.style.display = 'block';
                renderPageToCanvas(rightPage, elCanvasR, ctxR, checkDone);
            } else {
                elCanvasR.style.display = 'none';
            }

            elPageInput.value = currentPage;
            updateTocHighlight();
        }

        function goToPage(num) {
            num = Math.max(1, Math.min(num, totalPages));
            var oldPage = currentPage;
            currentPage = num;

            if (oldPage !== num && !flipping) {
                animateFlip(oldPage < num ? 'forward' : 'backward', function () {
                    renderCurrent();
                });
            } else {
                renderCurrent();
            }
            elCanvasWrap.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function calcFitScale() {
            if (!pdfDoc) return;
            pdfDoc.getPage(currentPage).then(function (page) {
                var vp = page.getViewport({ scale: 1 });
                var wrapW = elCanvasWrap.clientWidth - 48;
                var wrapH = elCanvasWrap.clientHeight - 48;
                var pageW = doubleMode ? vp.width * 2 + 6 : vp.width;
                scale = Math.min(wrapW / pageW, wrapH / vp.height, 2.5);
                scale = Math.max(scale, 0.3);
                updateZoomDisplay();
                renderCurrent();
            });
        }

        function updateZoomDisplay() {
            elZoomLevel.textContent = Math.round(scale * 100) + ' %';
        }

        /* ═══════════════════════════════════════
           3. Double-page mode
           ═══════════════════════════════════════ */

        function setDoubleMode(on) {
            doubleMode = on;
            elBook.classList.toggle('pdk-single', !on);
            elBook.classList.toggle('pdk-double', on);
            var btn = $('.pdk-toggle-double');
            if (btn) btn.classList.toggle('pdk-btn-active', on);
            calcFitScale();
        }

        /* ═══════════════════════════════════════
           4. Page flip animation
           ═══════════════════════════════════════ */

        function animateFlip(dir, cb) {
            if (flipping) { cb(); return; }
            flipping = true;
            elBook.classList.add('pdk-flipping');

            var container = document.createElement('div');
            container.className = 'pdk-flip-container pdk-flip-' + dir;

            var flipCanvas = document.createElement('canvas');
            container.appendChild(flipCanvas);

            var srcCanvas = dir === 'forward' ? elCanvasL : (doubleMode ? elCanvasR : elCanvasL);
            flipCanvas.width  = srcCanvas.width;
            flipCanvas.height = srcCanvas.height;
            flipCanvas.style.width  = srcCanvas.style.width;
            flipCanvas.style.height = srcCanvas.style.height;

            var flipCtx = flipCanvas.getContext('2d');
            flipCtx.drawImage(srcCanvas, 0, 0);

            elBook.appendChild(container);

            setTimeout(function () {
                elBook.removeChild(container);
                elBook.classList.remove('pdk-flipping');
                flipping = false;
                cb();
            }, 500);
        }

        /* ═══════════════════════════════════════
           5. TOC highlight
           ═══════════════════════════════════════ */

        function updateTocHighlight() {
            var active = null;
            $$('.pdk-item').forEach(function (a) {
                a.classList.remove('pdk-active');
                var p = parseInt(a.dataset.page, 10);
                if (p <= currentPage) active = a;
            });
            if (active) {
                active.classList.add('pdk-active');
                var ch = active.closest('.pdk-chapter');
                if (ch && !ch.classList.contains('pdk-open')) {
                    $$('.pdk-chapter').forEach(function (c) { c.classList.remove('pdk-open'); });
                    ch.classList.add('pdk-open');
                }
                if (active.scrollIntoView) {
                    active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
        }

        /* ═══════════════════════════════════════
           6. Text extraction (background)
           ═══════════════════════════════════════ */

        function extractAllText() {
            var idx = 1;
            function next() {
                if (idx > totalPages) { textExtracted = true; return; }
                pdfDoc.getPage(idx).then(function (page) {
                    return page.getTextContent();
                }).then(function (tc) {
                    pageTexts[idx] = tc.items.map(function (i) { return i.str; }).join(' ').toLowerCase();
                    idx++;
                    next();
                });
            }
            next();
        }

        /* ═══════════════════════════════════════
           7. Search
           ═══════════════════════════════════════ */

        var searchTimeout;

        function onSearchInput() {
            clearTimeout(searchTimeout);
            var q = elSearch.value.trim().toLowerCase();
            elSearchClear.style.display = q ? 'block' : 'none';
            if (!q) { resetSearch(); return; }
            searchTimeout = setTimeout(function () { performSearch(q); }, 200);
        }

        function performSearch(query) {
            var tocHitPages = new Set();
            var chapterHits = new Set();

            $$('.pdk-item').forEach(function (a) {
                var label = (a.querySelector('.pdk-item-label').textContent || '').toLowerCase();
                var match = label.indexOf(query) !== -1;
                a.classList.toggle('pdk-search-hit', match);
                a.classList.toggle('pdk-search-hidden', !match);
                if (match) {
                    tocHitPages.add(parseInt(a.dataset.page, 10));
                    chapterHits.add(a.closest('.pdk-chapter').dataset.chapter);
                    a.querySelector('.pdk-item-label').innerHTML = highlightText(a.querySelector('.pdk-item-label').textContent, query);
                } else {
                    a.querySelector('.pdk-item-label').innerHTML = escHTML(a.querySelector('.pdk-item-label').textContent);
                }
            });

            $$('.pdk-chapter').forEach(function (ch) {
                var cIdx = ch.dataset.chapter;
                var chLabel = (ch.querySelector('.pdk-chapter-label').textContent || '').toLowerCase();
                var chMatch = chLabel.indexOf(query) !== -1;
                if (chMatch) {
                    ch.classList.remove('pdk-search-hidden'); ch.classList.add('pdk-open');
                    ch.querySelectorAll('.pdk-item').forEach(function (a) { a.classList.remove('pdk-search-hidden'); a.classList.add('pdk-search-hit'); });
                    ch.querySelector('.pdk-chapter-label').innerHTML = highlightText(ch.querySelector('.pdk-chapter-label').textContent, query);
                } else if (chapterHits.has(cIdx)) {
                    ch.classList.remove('pdk-search-hidden'); ch.classList.add('pdk-open');
                    ch.querySelector('.pdk-chapter-label').innerHTML = escHTML(ch.querySelector('.pdk-chapter-label').textContent);
                } else {
                    ch.classList.add('pdk-search-hidden');
                    ch.querySelector('.pdk-chapter-label').innerHTML = escHTML(ch.querySelector('.pdk-chapter-label').textContent);
                }
            });

            var pdfHitPages = [];
            if (textExtracted) {
                for (var pn = 1; pn <= totalPages; pn++) {
                    if (pageTexts[pn] && pageTexts[pn].indexOf(query) !== -1) pdfHitPages.push(pn);
                }
            }

            var allSet = new Set();
            tocHitPages.forEach(function(p){ allSet.add(p); });
            pdfHitPages.forEach(function(p){ allSet.add(p); });
            var allHitPages = Array.from(allSet).sort(function (a, b) { return a - b; });

            if (allHitPages.length) {
                var extraPdf = pdfHitPages.filter(function (p) { return !tocHitPages.has(p); });
                var msg = allHitPages.length + ' Treffer gefunden';
                if (extraPdf.length) {
                    msg += ' · PDF-Seiten: ' + extraPdf.slice(0, 15).join(', ');
                    if (extraPdf.length > 15) msg += '…';
                }
                elSearchInfo.textContent = msg;
                elSearchInfo.style.display = 'block';
                if (allHitPages.indexOf(currentPage) === -1) goToPage(allHitPages[0]);
            } else {
                elSearchInfo.textContent = 'Keine Treffer für „' + query + '"';
                elSearchInfo.style.display = 'block';
            }
        }

        function resetSearch() {
            elSearchInfo.style.display = 'none';
            $$('.pdk-chapter').forEach(function (ch) { ch.classList.remove('pdk-search-hidden', 'pdk-open'); });
            $$('.pdk-item').forEach(function (a) {
                a.classList.remove('pdk-search-hit', 'pdk-search-hidden', 'pdk-active');
                var el = a.querySelector('.pdk-item-label');
                el.innerHTML = escHTML(el.textContent);
            });
            $$('.pdk-chapter-label').forEach(function (el) { el.innerHTML = escHTML(el.textContent); });
            updateTocHighlight();
        }

        /* ═══════════════════════════════════════
           8. Event Listeners
           ═══════════════════════════════════════ */

        function bindEvents() {
            elSearch.addEventListener('input', onSearchInput);
            elSearchClear.addEventListener('click', function () {
                elSearch.value = ''; elSearchClear.style.display = 'none';
                resetSearch(); elSearch.focus();
            });

            // Page nav
            $('.pdk-prev').addEventListener('click', function () {
                goToPage(currentPage - (doubleMode ? 2 : 1));
            });
            $('.pdk-next').addEventListener('click', function () {
                goToPage(currentPage + (doubleMode ? 2 : 1));
            });
            elPageInput.addEventListener('change', function () { goToPage(parseInt(this.value, 10) || 1); });
            elPageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') goToPage(parseInt(this.value, 10) || 1);
            });

            // Zoom
            $('.pdk-zoom-in').addEventListener('click', function () {
                scale = Math.min(scale + 0.2, 4); updateZoomDisplay(); renderCurrent();
            });
            $('.pdk-zoom-out').addEventListener('click', function () {
                scale = Math.max(scale - 0.2, 0.3); updateZoomDisplay(); renderCurrent();
            });
            $('.pdk-zoom-fit').addEventListener('click', calcFitScale);

            // Sidebar toggle
            $('.pdk-toggle-sidebar').addEventListener('click', function () {
                elSidebar.classList.toggle('pdk-hidden');
                setTimeout(calcFitScale, 400);
            });

            // Double-page toggle
            $('.pdk-toggle-double').addEventListener('click', function () {
                setDoubleMode(!doubleMode);
            });

            // Fullscreen
            $('.pdk-fullscreen').addEventListener('click', function () {
                if (!document.fullscreenElement) {
                    elRoot.requestFullscreen().catch(function () { elRoot.classList.toggle('pdk-fullscreen'); });
                } else {
                    document.exitFullscreen();
                }
            });
            document.addEventListener('fullscreenchange', function () {
                if (document.fullscreenElement === elRoot || (!document.fullscreenElement && elRoot.classList.contains('pdk-fullscreen'))) {
                    elRoot.classList.toggle('pdk-fullscreen', !!document.fullscreenElement);
                    setTimeout(calcFitScale, 300);
                }
            });

            // Click zones on the book
            $('.pdk-click-prev').addEventListener('click', function () {
                goToPage(currentPage - (doubleMode ? 2 : 1));
            });
            $('.pdk-click-next').addEventListener('click', function () {
                goToPage(currentPage + (doubleMode ? 2 : 1));
            });

            // Keyboard (only when this instance is focused/hovered)
            document.addEventListener('keydown', function (e) {
                if (!elRoot.contains(document.activeElement) && document.activeElement !== document.body) return;
                if (e.target.classList.contains('pdk-search') || e.target.classList.contains('pdk-page-input')) return;
                var step = doubleMode ? 2 : 1;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); goToPage(currentPage + step); }
                if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); goToPage(currentPage - step); }
                if (e.key === 'Home') { e.preventDefault(); goToPage(1); }
                if (e.key === 'End')  { e.preventDefault(); goToPage(totalPages); }
            });

            // Resize
            var rt;
            window.addEventListener('resize', function () {
                clearTimeout(rt); rt = setTimeout(calcFitScale, 250);
            });
        }

        /* ═══════════════════════════════════════
           9. Helpers
           ═══════════════════════════════════════ */

        function escHTML(s) {
            var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
        }
        function highlightText(text, query) {
            var esc = escHTML(text);
            var qEsc = escHTML(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return esc.replace(new RegExp('(' + qEsc + ')', 'gi'), '<mark class="pdk-highlight">$1</mark>');
        }

        /* ═══════════════════════════════════════
           10. Init this instance
           ═══════════════════════════════════════ */

        buildTOC();
        bindEvents();
        setDoubleMode(false);
        initPDF();

        var fitCheck = setInterval(function () {
            if (pdfDoc) { clearInterval(fitCheck); calcFitScale(); }
        }, 300);
    }

    /* ═══════════════════════════════════════════════
       Boot all instances
       ═══════════════════════════════════════════════ */

    function bootAll() {
        var instances = window.pdkInstances || [];

        // Backward compat: v2.x single pdkData
        if (!instances.length && window.pdkData) {
            instances.push(window.pdkData);
        }

        instances.forEach(function (data) {
            try { initInstance(data); }
            catch (e) { console.error('PDK init error:', e); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }

})();

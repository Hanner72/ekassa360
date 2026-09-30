/**
 * Durchsuchbare Artikel-Auswahl für Angebot/Auftrag/Verkaufsrechnung (ersetzt das bisherige
 * einfache <select> mit allen Artikeln, das bei vielen Artikeln unübersichtlich wurde).
 *
 * Erwartet auf der jeweiligen Seite:
 *   - eine globale Variable `artikelDaten` (Array von Artikel-Objekten mit u.a. id,
 *     artikelnummer, bezeichnung, einzelpreis_netto) - existiert bereits auf allen drei Seiten.
 *   - eine globale Funktion `uebernehmeArtikel(element)`, die anhand von `element.value`
 *     (Artikel-ID) und `element.closest('tr')` die restlichen Positionsfelder befüllt -
 *     existiert bereits identisch auf allen drei Seiten und wird hier unverändert
 *     weiterverwendet (funktioniert unabhängig davon, ob `element` ein <select> oder wie hier
 *     ein <input type="hidden"> ist, da nur .value und .closest('tr') gelesen werden).
 *
 * Arbeitet komplett über Event-Delegation auf `document`, damit auch dynamisch per
 * addPositionRow() neu eingefügte Zeilen ohne weiteren Aufwand funktionieren.
 */
(function () {
    const MAX_TREFFER = 50;

    function label(artikel) {
        const nr = artikel.artikelnummer ? artikel.artikelnummer + ' - ' : '';
        return nr + artikel.bezeichnung;
    }

    function preisText(artikel) {
        const preis = parseFloat(artikel.einzelpreis_netto || 0).toFixed(2).replace('.', ',');
        return '€ ' + preis;
    }

    /**
     * HTML für eine Artikel-Zelle - vom addPositionRow() der jeweiligen Seite aufgerufen.
     * Die Trefferliste ist bewusst `position: fixed` (Position/Breite wird bei jedem Öffnen
     * per JS aus der Bildschirmposition des Suchfelds berechnet, siehe positioniereListe()) -
     * die Positionstabelle steckt in einem `.table-responsive`-Wrapper, dessen `overflow-x:
     * auto` Browser automatisch auch `overflow-y` auf `auto` setzen lässt. Mit `position:
     * absolute` (relativ zur Tabellenzelle) würde die Liste dadurch am Tabellenrand
     * abgeschnitten statt frei darüber zu schweben.
     */
    window.artikelPickerZelle = function (artikelId) {
        const artikel = (window.artikelDaten || []).find(a => a.id == artikelId);
        const wert = artikel ? label(artikel) : '';
        return `<div class="artikel-picker position-relative">
            <input type="hidden" name="pos_artikel_id[]" value="${artikelId || ''}">
            <input type="text" class="form-control form-control-sm artikel-picker-such" placeholder="Artikel suchen..." autocomplete="off" value="${escapeHtmlPicker(wert)}">
            <div class="list-group artikel-picker-liste position-fixed shadow-sm" tabindex="-1" style="z-index:1055; max-height:240px; overflow-y:auto; overscroll-behavior:contain; outline:none; display:none;"></div>
        </div>`;
    };

    function escapeHtmlPicker(str) {
        return (str || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function picker(suchInput) {
        const wrap = suchInput.closest('.artikel-picker');
        return {
            wrap,
            suchInput,
            hiddenInput: wrap.querySelector('input[type="hidden"]'),
            liste: wrap.querySelector('.artikel-picker-liste')
        };
    }

    function treffer(query) {
        const daten = window.artikelDaten || [];
        const q = query.trim().toLowerCase();
        const gefiltert = q === ''
            ? daten
            : daten.filter(a => label(a).toLowerCase().includes(q));
        return gefiltert.slice(0, MAX_TREFFER);
    }

    /** Position/Breite der (position: fixed) Trefferliste an das Suchfeld auf dem Bildschirm anpassen. */
    function positioniereListe(p) {
        const rect = p.suchInput.getBoundingClientRect();
        p.liste.style.left = rect.left + 'px';
        p.liste.style.width = rect.width + 'px';
        // Standardmäßig unterhalb des Suchfelds, außer es ist unten zu wenig Platz (dann darüber) -
        // 240px = max-height der Liste, 8px Sicherheitsabstand zum Fensterrand.
        const platzUnten = window.innerHeight - rect.bottom;
        if (platzUnten < 240 && rect.top > platzUnten) {
            p.liste.style.top = 'auto';
            p.liste.style.bottom = (window.innerHeight - rect.top) + 'px';
        } else {
            p.liste.style.bottom = 'auto';
            p.liste.style.top = rect.bottom + 'px';
        }
    }

    function zeigeListe(p, query) {
        const gefunden = treffer(query);
        if (gefunden.length === 0) {
            p.liste.innerHTML = '<div class="list-group-item text-muted small">Keine Artikel gefunden</div>';
        } else {
            p.liste.innerHTML = gefunden.map((a, i) =>
                `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small artikel-picker-item" data-index="${i}" data-id="${a.id}">
                    <div>${escapeHtmlPicker(label(a))}</div>
                    <div class="text-muted">${preisText(a)}</div>
                </button>`
            ).join('');
        }
        p.liste.dataset.treffer = JSON.stringify(gefunden.map(a => a.id));
        positioniereListe(p);
        p.liste.style.display = 'block';
        p.liste.querySelectorAll('.artikel-picker-item').forEach(el => el.classList.remove('active'));
    }

    function verstecke(p) {
        p.liste.style.display = 'none';
    }

    function waehleArtikel(p, artikelId) {
        const artikel = (window.artikelDaten || []).find(a => a.id == artikelId);
        if (!artikel) return;
        p.hiddenInput.value = artikel.id;
        p.suchInput.value = label(artikel);
        verstecke(p);
        if (typeof window.uebernehmeArtikel === 'function') {
            window.uebernehmeArtikel(p.hiddenInput);
        }
    }

    document.addEventListener('input', function (e) {
        if (!e.target.matches('.artikel-picker-such')) return;
        const p = picker(e.target);
        if (e.target.value.trim() === '') {
            p.hiddenInput.value = '';
        }
        zeigeListe(p, e.target.value);
    });

    document.addEventListener('focusin', function (e) {
        if (!e.target.matches('.artikel-picker-such')) return;
        const p = picker(e.target);
        zeigeListe(p, e.target.value);
    });

    // Schließen erst, wenn der Fokus den Picker (Suchfeld + Liste) ganz verlässt. Klicks in die
    // Liste - auch auf deren Scrollbar - geben den Fokus an die Liste (tabindex="-1") bzw. an
    // einen Eintrag weiter und lassen sie damit offen. Die kurze Verzögerung deckt Browser ab,
    // die beim Klick kein relatedTarget liefern (z.B. Safari bei Buttons).
    document.addEventListener('focusout', function (e) {
        const wrap = e.target.closest && e.target.closest('.artikel-picker');
        if (!wrap) return;
        if (e.relatedTarget && wrap.contains(e.relatedTarget)) return;
        const p = picker(wrap.querySelector('.artikel-picker-such'));
        setTimeout(() => {
            if (!wrap.contains(document.activeElement)) verstecke(p);
        }, 150);
    });

    document.addEventListener('mousedown', function (e) {
        const item = e.target.closest('.artikel-picker-item');
        if (!item) return;
        const suchInput = item.closest('.artikel-picker').querySelector('.artikel-picker-such');
        waehleArtikel(picker(suchInput), item.dataset.id);
    });

    document.addEventListener('keydown', function (e) {
        if (!e.target.matches('.artikel-picker-such')) return;
        const p = picker(e.target);
        if (p.liste.style.display === 'none') return;
        const items = Array.from(p.liste.querySelectorAll('.artikel-picker-item'));
        if (items.length === 0) return;
        let aktiv = items.findIndex(el => el.classList.contains('active'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            aktiv = (aktiv + 1) % items.length;
            items.forEach(el => el.classList.remove('active'));
            items[aktiv].classList.add('active');
            items[aktiv].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            aktiv = aktiv <= 0 ? items.length - 1 : aktiv - 1;
            items.forEach(el => el.classList.remove('active'));
            items[aktiv].classList.add('active');
            items[aktiv].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const ziel = aktiv >= 0 ? items[aktiv] : items[0];
            waehleArtikel(p, ziel.dataset.id);
        } else if (e.key === 'Escape') {
            verstecke(p);
        }
    });

    // Klick außerhalb eines Pickers schließt alle offenen Listen (auch falls der Browser dabei
    // kein focusout auslöst).
    document.addEventListener('mousedown', function (e) {
        if (e.target.closest('.artikel-picker')) return;
        document.querySelectorAll('.artikel-picker-liste').forEach(liste => {
            liste.style.display = 'none';
        });
    });

    // Bei Scrollen der Seite/Tabelle oder Fenster-Resize (z.B. Bildschirmtastatur am Handy)
    // passt sich die fixed-positionierte Liste nicht automatisch an - daher neu ausrichten.
    // Scroll-Events aus der Liste selbst werden ignoriert, sonst wäre sie nicht scrollbar.
    // `true` (Capture-Phase) ist nötig, damit auch Scroll-Events von inneren, nicht
    // bubbelnden Containern ankommen.
    function positioniereOffeneListen(e) {
        if (e && e.target instanceof Element && e.target.closest('.artikel-picker-liste')) return;
        document.querySelectorAll('.artikel-picker-liste').forEach(liste => {
            if (liste.style.display === 'none') return;
            const suchInput = liste.closest('.artikel-picker').querySelector('.artikel-picker-such');
            positioniereListe(picker(suchInput));
        });
    }
    document.addEventListener('scroll', positioniereOffeneListen, true);
    window.addEventListener('resize', positioniereOffeneListen);
})();

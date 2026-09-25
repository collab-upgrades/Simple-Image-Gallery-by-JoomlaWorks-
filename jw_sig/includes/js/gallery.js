/* New local gallery viewer, 2026-09-24; GPL-2.0. */
/* Simple Image Gallery: local viewer; no external dependencies. */
(() => {
  'use strict';
  let dialog, image, caption, counter, previous, next, zoom;
  let links = [];
  let index = 0;
  let returnFocus = null;

  function show(position) {
    if (!links.length) return;
    index = (position + links.length) % links.length;
    const link = links[index];
    image.src = link.href;
    image.alt = link.querySelector('img')?.alt || '';
    caption.textContent = link.getAttribute('title') || image.alt;
    counter.textContent = `${index + 1} / ${links.length}`;
    previous.disabled = next.disabled = links.length < 2;
    dialog.classList.remove('sigZoomed');
    zoom.setAttribute('aria-pressed', 'false');
  }

  function setup() {
    dialog = document.createElement('dialog');
    dialog.className = 'sigViewer';
    dialog.setAttribute('aria-label', 'Image gallery');
    dialog.innerHTML = '<div class="sigViewerBar"><button type="button" class="sigViewerPrevious" aria-label="Previous image">&#x2039;</button><span class="sigViewerCounter" aria-live="polite"></span><button type="button" class="sigViewerNext" aria-label="Next image">&#x203a;</button><button type="button" class="sigViewerZoom" aria-label="Zoom image" aria-pressed="false">&#x2922;</button><button type="button" class="sigViewerClose" aria-label="Close gallery">&#xd7;</button></div><div class="sigViewerStage"><img class="sigViewerImage" alt=""></div><p class="sigViewerCaption"></p>';
    document.body.append(dialog);
    image = dialog.querySelector('.sigViewerImage');
    caption = dialog.querySelector('.sigViewerCaption');
    counter = dialog.querySelector('.sigViewerCounter');
    previous = dialog.querySelector('.sigViewerPrevious');
    next = dialog.querySelector('.sigViewerNext');
    zoom = dialog.querySelector('.sigViewerZoom');
    previous.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    zoom.addEventListener('click', () => {
      const active = dialog.classList.toggle('sigZoomed');
      zoom.setAttribute('aria-pressed', String(active));
    });
    dialog.querySelector('.sigViewerClose').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        show(index + (event.key === 'ArrowRight' ? 1 : -1));
      }
    });
    dialog.addEventListener('close', () => {
      image.removeAttribute('src');
      links = [];
      returnFocus?.focus();
      returnFocus = null;
    });
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest?.('ul.sigFreeClassic a.sigFreeLink');
    if (!link || typeof HTMLDialogElement === 'undefined') return;
    const group = link.closest('ul.sigFreeClassic');
    if (!group) return;
    if (!dialog) setup();
    links = Array.from(group.querySelectorAll('a.sigFreeLink[href]'));
    index = links.indexOf(link);
    if (index < 0) return;
    event.preventDefault();
    returnFocus = link;
    show(index);
    if (!dialog.open) dialog.showModal();
  });
})();

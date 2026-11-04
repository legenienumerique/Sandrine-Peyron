/* Sandrine Peyron : menu, respiration, filtres, onglets, agenda et réservation. */
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const calme = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // En-tête
  const top = $('.top');
  const surScroll = () => top && top.classList.toggle('scrolled', scrollY > 8);
  addEventListener('scroll', surScroll, { passive: true }); surScroll();

  // Menu mobile
  const burger = $('.burger');
  if (burger) {
    const fermer = () => { document.body.classList.remove('mopen'); burger.setAttribute('aria-expanded', 'false'); burger.textContent = 'Menu'; };
    burger.addEventListener('click', () => {
      const ouvert = document.body.classList.toggle('mopen');
      burger.setAttribute('aria-expanded', String(ouvert));
      burger.textContent = ouvert ? 'Fermer' : 'Menu';
    });
    $$('.mnav a').forEach(a => a.addEventListener('click', fermer));
    addEventListener('keydown', e => { if (e.key === 'Escape') fermer(); });
  }

  // Respiration : 4 s d'inspiration, 6 s d'expiration (même rythme que l'animation CSS)
  const souffle = $('.souffle');
  if (souffle) {
    const mot = $('.mot', souffle), btn = $('.pause', souffle);
    let minuteur = null, phase = 0;
    const cycle = () => {
      mot.style.opacity = 0;
      setTimeout(() => { mot.textContent = phase ? 'Expirez…' : 'Inspirez…'; mot.style.opacity = 1; }, 450);
      minuteur = setTimeout(() => { phase = 1 - phase; cycle(); }, phase ? 6000 : 4000);
    };
    const relancer = () => {
      $$('.vagues', souffle).forEach(v => { v.style.animation = 'none'; void v.getBoundingClientRect(); v.style.animation = ''; });
      phase = 0; cycle();
    };
    if (calme) { mot.textContent = 'Respirez'; btn.hidden = true; }
    else {
      cycle();
      btn.addEventListener('click', () => {
        const arret = souffle.classList.toggle('arret');
        btn.textContent = arret ? 'Reprendre' : 'Pause';
        btn.setAttribute('aria-pressed', String(arret));
        if (arret) { clearTimeout(minuteur); mot.textContent = 'Respirez'; } else relancer();
      });
    }
  }

  // Filtres (groupes, activités)
  $$('[data-filtres]').forEach(barre => {
    const cible = $(barre.dataset.filtres);
    barre.addEventListener('click', e => {
      const b = e.target.closest('button'); if (!b) return;
      $$('button', barre).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
      const f = b.dataset.f;
      $$('[data-cat]', cible).forEach(el => { el.hidden = f !== 'tous' && el.dataset.cat !== f; });
    });
  });

  // Onglets
  $$('[role=tablist]').forEach(liste => {
    const onglets = $$('[role=tab]', liste);
    const choisir = t => {
      onglets.forEach(o => { const actif = o === t; o.setAttribute('aria-selected', String(actif)); o.tabIndex = actif ? 0 : -1; $('#' + o.getAttribute('aria-controls')).hidden = !actif; });
      t.focus();
    };
    onglets.forEach((o, i) => {
      o.addEventListener('click', () => choisir(o));
      o.addEventListener('keydown', e => {
        if (e.key === 'ArrowRight') choisir(onglets[(i + 1) % onglets.length]);
        if (e.key === 'ArrowLeft') choisir(onglets[(i - 1 + onglets.length) % onglets.length]);
      });
    });
  });

  // Agenda : créneaux réels lus sur /api/creneaux
  const fenetre = $('dialog.resa');
  $$('.agenda[data-activite]').forEach(async ag => {
    const jours = $('.jours', ag), cren = $('.creneaux', ag), titre = $('.choix', ag);
    let donnees;
    try { donnees = await (await fetch('/api/creneaux?activite=' + encodeURIComponent(ag.dataset.activite))).json(); }
    catch { jours.innerHTML = '<p>Les créneaux n’ont pas pu être chargés. Rechargez la page ou appelez Sandrine.</p>'; return; }
    jours.innerHTML = '';
    let premier = null;
    donnees.jours.forEach(j => {
      const d = new Date(j.jour + 'T12:00:00');
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'jour'; b.setAttribute('aria-pressed', 'false');
      b.innerHTML = `${d.toLocaleDateString('fr-FR', { weekday: 'short' })}<b>${d.getDate()}</b>`;
      b.setAttribute('aria-label', j.libelle + (j.creneaux.length ? `, ${j.creneaux.length} créneau${j.creneaux.length > 1 ? 'x' : ''}` : ', aucun créneau'));
      b.disabled = !j.creneaux.length;
      b.addEventListener('click', () => {
        $$('.jour', jours).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
        titre.textContent = j.libelle.charAt(0).toUpperCase() + j.libelle.slice(1);
        cren.innerHTML = '';
        j.creneaux.forEach(c => {
          const x = document.createElement('button');
          x.type = 'button'; x.textContent = c.heure; x.title = c.lieu;
          x.addEventListener('click', () => ouvrir({ type: 'rdv', activite: ag.dataset.activite, debut: c.debut, titre: ag.dataset.titre, quand: `${j.libelle} à ${c.heure}`, duree: ag.dataset.duree, lieu: c.lieu, prix: ag.dataset.prix }));
          cren.append(x);
        });
      });
      jours.append(b);
      if (!premier && j.creneaux.length) premier = b;
    });
    if (premier) premier.click();
    else jours.innerHTML = '<p>Aucun créneau libre dans les quatre prochaines semaines. Appelez Sandrine pour trouver une date.</p>';
  });

  // Fenêtre de réservation : récapitulatif, puis coordonnées ; l'envoi part vers le paiement Mollie
  let etape = 1, choix = {};
  const echapper = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const rendre = () => {
    $$('.etapes li', fenetre).forEach((li, i) => li.classList.toggle('ok', i < etape));
    $$('[data-etape]', fenetre).forEach(p => { p.hidden = +p.dataset.etape !== etape; });
    $('.recap', fenetre).innerHTML = `<p><b>${echapper(choix.titre)}</b></p><p>${echapper(choix.quand)}</p>` +
      (choix.duree ? `<p>Durée : ${echapper(choix.duree)}</p>` : '') + `<p>Lieu : ${echapper(choix.lieu)}</p><p>Tarif : ${echapper(choix.prix)}</p>`;
    $('.prix', fenetre).textContent = choix.prix;
    $('.ado-note', fenetre).hidden = !choix.ado;
    const h = $(`[data-etape="${etape}"] h2`, fenetre); h && h.focus();
  };
  function ouvrir(d) {
    if (!fenetre) return;
    choix = d; etape = 1;
    const f = $('form', fenetre);
    ['type', 'groupe', 'activite', 'debut'].forEach(k => { f.elements[k].value = d[k] ?? ''; });
    rendre(); fenetre.showModal();
  }
  $$('[data-resa]').forEach(b => b.addEventListener('click', () => ouvrir(JSON.parse(b.dataset.resa))));
  if (fenetre) {
    $('.x', fenetre).addEventListener('click', () => fenetre.close());
    fenetre.addEventListener('click', e => { if (e.target === fenetre) fenetre.close(); });
    $$('[data-suite]', fenetre).forEach(b => b.addEventListener('click', () => { etape++; rendre(); }));
    $$('[data-retour]', fenetre).forEach(b => b.addEventListener('click', () => { etape--; rendre(); }));
    $('form', fenetre).addEventListener('submit', e => {
      const btn = $('button[type=submit]', fenetre);
      if (btn.disabled) { e.preventDefault(); return; }
      btn.disabled = true; btn.textContent = 'Redirection vers le paiement…';
    });
  }
})();
// Retour arrière depuis la page de paiement : le bouton redevient utilisable
addEventListener('pageshow', () => document.querySelectorAll('dialog.resa button[type=submit]').forEach(b => { b.disabled = false; b.innerHTML = 'Payer <span class="prix"></span>'; }));

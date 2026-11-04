/* Sandrine Peyron — maquette. Aucun envoi de données : la réservation s'arrête avant le paiement. */
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

  // Agenda d'exemple : plages fictives, en attendant les vraies disponibilités de Sandrine
  const PLAGES = {
    sophro: { 1: ['09:00', '10:00', '11:00'], 2: ['14:00', '15:00', '16:00', '17:00'], 3: ['09:00', '10:00', '11:00'], 4: ['14:00', '15:00', '16:00'], 5: ['09:00', '10:00'] },
    emdr: { 2: ['09:00', '10:30'], 4: ['09:00', '10:30'], 5: ['14:00', '15:30'] }
  };
  const DUREE = { sophro: '1 h', emdr: '1 h 30' };
  const NOM = { sophro: 'Séance individuelle de sophrologie', emdr: 'Séance individuelle d’EMDR-DSA' };
  $$('.agenda').forEach(ag => {
    const type = ag.dataset.type, jours = $('.jours', ag), cren = $('.creneaux', ag), titre = $('.choix', ag);
    const fmtJ = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
    const fmtL = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    const d0 = new Date(); d0.setHours(0, 0, 0, 0);
    let premier = null;
    for (let i = 1; i <= 14; i++) {
      const d = new Date(d0); d.setDate(d.getDate() + i);
      // Quelques créneaux retirés pour imiter des rendez-vous déjà pris
      const libres = (PLAGES[type][d.getDay()] || []).filter((_, k) => (d.getDate() + k) % 3);
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'jour'; b.setAttribute('aria-pressed', 'false');
      b.innerHTML = `${fmtJ.format(d)}<b>${d.getDate()}</b>`;
      b.setAttribute('aria-label', fmtL.format(d) + (libres.length ? `, ${libres.length} créneaux` : ', complet'));
      b.disabled = !libres.length;
      b.addEventListener('click', () => {
        $$('.jour', jours).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
        titre.textContent = fmtL.format(d);
        cren.innerHTML = '';
        libres.forEach(h => {
          const c = document.createElement('button');
          c.type = 'button'; c.textContent = h.replace(':', ' h ');
          c.addEventListener('click', () => ouvrir({ titre: NOM[type], quand: `${fmtL.format(d)} à ${h.replace(':', ' h ')}`, duree: DUREE[type], lieu: ag.dataset.lieu, prix: 'à fixer avec Sandrine' }));
          cren.append(c);
        });
      });
      jours.append(b);
      if (!premier && libres.length) premier = b;
    }
    premier && premier.click();
  });

  // Fenêtre de réservation
  const dlg = $('dialog.resa');
  let etape = 1, donnees = {};
  const rendre = () => {
    $$('.etapes li', dlg).forEach((li, i) => li.classList.toggle('ok', i < etape));
    $$('[data-etape]', dlg).forEach(p => { p.hidden = +p.dataset.etape !== etape; });
    $$('.recap', dlg).forEach(r => {
      r.innerHTML = `<p><b>${donnees.titre}</b></p><p>${donnees.quand}</p>` +
        (donnees.duree ? `<p>Durée : ${donnees.duree}</p>` : '') + `<p>Lieu : ${donnees.lieu}</p><p>Tarif : ${donnees.prix}</p>`;
    });
    const h = $(`[data-etape="${etape}"] h2`, dlg); h && h.focus();
  };
  function ouvrir(d) { if (!dlg) return; donnees = d; etape = 1; $('form', dlg).reset(); rendre(); dlg.showModal(); }
  $$('[data-resa]').forEach(b => b.addEventListener('click', () => ouvrir(JSON.parse(b.dataset.resa))));
  if (dlg) {
    $('.x', dlg).addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    $$('[data-suite]', dlg).forEach(b => b.addEventListener('click', () => {
      if (etape === 2) { const champs = $$('[data-etape="2"] input', dlg); const ko = champs.find(c => !c.checkValidity()); if (ko) { ko.reportValidity(); return; } }
      etape++; rendre();
    }));
    $$('[data-retour]', dlg).forEach(b => b.addEventListener('click', () => { etape--; rendre(); }));
  }
})();

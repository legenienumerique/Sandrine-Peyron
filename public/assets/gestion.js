/* Espace de gestion : confirmations avant action, code QR d'activation. */
document.querySelectorAll('[data-confirmer]').forEach(b => b.addEventListener('click', e => { if (!confirm(b.dataset.confirmer)) e.preventDefault(); }));
addEventListener('load', () => {
  const qr = document.getElementById('qr');
  if (qr && window.QRCode) new QRCode(qr, { text: qr.dataset.lien, width: 200, height: 200 });
});

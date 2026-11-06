-- Base du site de Sandrine Peyron. Montants en centimes, dates en heure de Paris (AAAA-MM-JJ HH:MM:SS).
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS activites (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL CHECK (type IN ('parole', 'sophro_groupe', 'individuel')),
  nom TEXT NOT NULL,
  resume TEXT NOT NULL DEFAULT '',
  duree_minutes INTEGER NOT NULL DEFAULT 60,
  tarif_centimes INTEGER,              -- NULL = tarif pas encore fixé : réservation fermée
  actif INTEGER NOT NULL DEFAULT 1,
  ordre INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS groupes (
  id INTEGER PRIMARY KEY,
  activite_id INTEGER NOT NULL REFERENCES activites(id),
  capacite INTEGER NOT NULL CHECK (capacite > 0),
  tarif_centimes INTEGER,              -- NULL = tarif de l'activité
  lieu TEXT NOT NULL DEFAULT 'Cabinet, Belleville-en-Beaujolais',
  horaire TEXT NOT NULL DEFAULT '',
  statut TEXT NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon', 'publie', 'annule')),
  cree_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS seances_groupe (
  id INTEGER PRIMARY KEY,
  groupe_id INTEGER NOT NULL REFERENCES groupes(id) ON DELETE CASCADE,
  debut TEXT NOT NULL,
  fin TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS seances_groupe_debut ON seances_groupe(debut);

CREATE TABLE IF NOT EXISTS disponibilites (
  id INTEGER PRIMARY KEY,
  jour INTEGER NOT NULL CHECK (jour BETWEEN 1 AND 7),  -- 1 = lundi
  debut TEXT NOT NULL,                 -- HH:MM
  fin TEXT NOT NULL,
  lieu TEXT NOT NULL DEFAULT 'Cabinet, Belleville-en-Beaujolais',
  activites TEXT NOT NULL DEFAULT ''   -- codes séparés par des virgules ; vide = toutes les séances individuelles
);

CREATE TABLE IF NOT EXISTS absences (
  id INTEGER PRIMARY KEY,
  debut TEXT NOT NULL,
  fin TEXT NOT NULL,
  motif TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS clients (
  id INTEGER PRIMARY KEY,
  prenom TEXT NOT NULL,
  nom TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  telephone TEXT NOT NULL DEFAULT '',
  consentement_le TEXT,
  anonymise INTEGER NOT NULL DEFAULT 0,
  cree_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS paiements (
  id INTEGER PRIMARY KEY,
  reference TEXT NOT NULL UNIQUE,
  mollie_id TEXT UNIQUE,
  montant_centimes INTEGER NOT NULL,
  statut TEXT NOT NULL DEFAULT 'cree',  -- cree, open, pending, authorized, paid, failed, canceled, expired
  rembourse_centimes INTEGER NOT NULL DEFAULT 0,
  checkout_url TEXT,
  paye_le TEXT,
  cree_le TEXT NOT NULL,
  maj_le TEXT NOT NULL
);

-- Une réservation = une inscription à un groupe ou un rendez-vous individuel
CREATE TABLE IF NOT EXISTS reservations (
  id INTEGER PRIMARY KEY,
  type TEXT NOT NULL CHECK (type IN ('groupe', 'rdv')),
  activite_id INTEGER NOT NULL REFERENCES activites(id),
  groupe_id INTEGER REFERENCES groupes(id),
  client_id INTEGER NOT NULL REFERENCES clients(id),
  debut TEXT NOT NULL,
  fin TEXT NOT NULL,
  lieu TEXT NOT NULL,
  montant_centimes INTEGER NOT NULL,
  statut TEXT NOT NULL CHECK (statut IN ('attente', 'confirmee', 'annulee')),
  expire_le TEXT,                       -- fin du blocage de la place sans paiement
  paiement_id INTEGER REFERENCES paiements(id),
  jeton TEXT NOT NULL UNIQUE,           -- lien d'annulation envoyé au client
  motif_annulation TEXT,
  annule_le TEXT,
  rappel_envoye INTEGER NOT NULL DEFAULT 0,
  cree_le TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS reservations_groupe ON reservations(groupe_id, statut);
CREATE INDEX IF NOT EXISTS reservations_debut ON reservations(debut, statut);

CREATE TABLE IF NOT EXISTS factures (
  id INTEGER PRIMARY KEY,
  numero TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL CHECK (type IN ('facture', 'avoir')),
  origine_id INTEGER REFERENCES factures(id),
  reservation_id INTEGER REFERENCES reservations(id),
  client_id INTEGER NOT NULL REFERENCES clients(id),
  montant_centimes INTEGER NOT NULL,
  donnees TEXT NOT NULL,                -- contenu figé au moment de l'émission (JSON)
  emise_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS compteurs (cle TEXT PRIMARY KEY, valeur INTEGER NOT NULL);

CREATE TABLE IF NOT EXISTS articles (
  id INTEGER PRIMARY KEY,
  titre TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  categorie TEXT NOT NULL CHECK (categorie IN ('article', 'compte_rendu')),
  resume TEXT NOT NULL DEFAULT '',
  contenu TEXT NOT NULL DEFAULT '',
  image TEXT,
  statut TEXT NOT NULL DEFAULT 'brouillon' CHECK (statut IN ('brouillon', 'publie')),
  publie_le TEXT,
  maj_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS parametres (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL);

CREATE TABLE IF NOT EXISTS utilisateurs (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  mot_de_passe TEXT NOT NULL,
  totp_secret TEXT,
  totp_actif INTEGER NOT NULL DEFAULT 0,
  totp_dernier INTEGER NOT NULL DEFAULT 0,  -- dernier pas de temps utilisé (pas de rejeu)
  cree_le TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tentatives (cle TEXT NOT NULL, quand INTEGER NOT NULL);
CREATE INDEX IF NOT EXISTS tentatives_cle ON tentatives(cle, quand);

CREATE TABLE IF NOT EXISTS journal (
  id INTEGER PRIMARY KEY,
  quand TEXT NOT NULL,
  qui TEXT NOT NULL,
  action TEXT NOT NULL,
  detail TEXT NOT NULL DEFAULT ''
);

-- Événements Mollie déjà traités par la réconciliation (remboursements, rétrofacturations) : jamais deux fois
CREATE TABLE IF NOT EXISTS mollie_evenements (
  id_mollie TEXT PRIMARY KEY,
  type TEXT NOT NULL,
  traite_le TEXT NOT NULL
);

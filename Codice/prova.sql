-- --------------------------------------------------------------------------
-- TABELLA: ruoli
-- --------------------------------------------------------------------------

CREATE TABLE ruoli (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livello INT NOT NULL,
    nome_ruolo VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO ruoli (livello, nome_ruolo) VALUES
(4, 'Admin'),
(3, 'Direttore filiale'),
(2, 'Caporeparto'),
(1, 'Utente base');


-- --------------------------------------------------------------------------
-- TABELLA: filiali
-- --------------------------------------------------------------------------

CREATE TABLE filiali (
    id INT PRIMARY KEY AUTO_INCREMENT,
    città VARCHAR(100) NOT NULL
);

INSERT INTO filiali (città) VALUES
('Milano'),
('Roma'),
('Napoli'),
('Torino'),
('Firenze');


-- --------------------------------------------------------------------------
-- TABELLA: utenti
-- --------------------------------------------------------------------------

CREATE TABLE utenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    nome_utente VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_ruolo INT,
    id_filiale INT,

    FOREIGN KEY (id_ruolo) REFERENCES ruoli(id),
    FOREIGN KEY (id_filiale) REFERENCES filiali(id)
);

INSERT INTO utenti (nome, cognome, nome_utente, password, id_ruolo, id_filiale) VALUES
('Luca', 'Colombo', 'admin', 'Admin!', 1, NULL),
('Marco', 'Bianchi', 'm.bianchi', 'Direttore1', 2, 1),
('Giulia', 'Ferraro', 'g.ferraro', 'Direttore2', 2, 2),
('Alessandro', 'Russo', 'a.russo', 'CapoRusso!', 3, 1),
('Chiara', 'Marino', 'c.marino', 'CapoMarino!', 3, 2),
('Paolo', 'Greco', 'p.greco', 'UtenteGreco!', 4, 1),
('Tommaso', 'Ricci', 't.ricci', 'UtenteRicci!', 4, 2);


-- --------------------------------------------------------------------------
-- TABELLA: categorie_prodotti
-- --------------------------------------------------------------------------

CREATE TABLE categorie_prodotti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_categoria VARCHAR(100) NOT NULL
);

INSERT INTO categorie_prodotti (nome_categoria) VALUES
('Calcio'),
('Basket'),
('Pallavolo'),
('Rugby'),
('Pallamano');


-- --------------------------------------------------------------------------
-- TABELLA: prodotti
-- --------------------------------------------------------------------------

CREATE TABLE prodotti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    prezzo INT NOT NULL,
    quantita_magazzino INT DEFAULT 0,
    id_categoria INT,

    FOREIGN KEY (id_categoria) REFERENCES categorie_prodotti(id)
);

INSERT INTO prodotti (nome, prezzo, quantita_magazzino, id_categoria) VALUES
('Pallone', 20, 50, 1),
('Porticina', 60, 7, 1),
('Pallone', 20, 200, 2),
('Canestro', 100, 80, 2),
('Rete', 80, 500, 3),
('Ginocchiere', 10, 300, 3),
('Pallone', 20, 30, 4),
('Scarpe', 150, 60, 4),
('Sagoma', 80, 150, 5);


-- --------------------------------------------------------------------------
-- TABELLA: vendite
-- --------------------------------------------------------------------------

CREATE TABLE vendite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data DATE NOT NULL,
    id_utente INT,

    FOREIGN KEY (id_utente) REFERENCES utenti(id)
);

INSERT INTO vendite (data, id_utente) VALUES
('2026-04-25', 1),
('2026-04-26', 6),
('2026-04-27', 7);


-- --------------------------------------------------------------------------
-- TABELLA: acquisti
-- --------------------------------------------------------------------------

CREATE TABLE acquisti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data DATE NOT NULL,
    id_utente INT,

    FOREIGN KEY (id_utente) REFERENCES utenti(id)
);

INSERT INTO acquisti (data, id_utente) VALUES
('2026-04-24', 1),
('2026-04-25', 1);


-- --------------------------------------------------------------------------
-- TABELLA: dettaglio_vendite
-- --------------------------------------------------------------------------

CREATE TABLE dettaglio_vendite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendita_id INT,
    prodotto_id INT,
    quantita INT NOT NULL,
    prezzo_unitario INT NOT NULL,

    FOREIGN KEY (vendita_id) REFERENCES vendite(id),
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id)
);

INSERT INTO dettaglio_vendite (vendita_id, prodotto_id, quantita, prezzo_unitario) VALUES
(1, 1, 2, 20),
(1, 2, 1, 60),
(2, 3, 3, 20),
(2, 9, 1, 80),
(3, 7, 1, 20),
(3, 8, 2, 150);


-- --------------------------------------------------------------------------
-- TABELLA: dettaglio_acquisti
-- --------------------------------------------------------------------------

CREATE TABLE dettaglio_acquisti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    acquisto_id INT,
    prodotto_id INT,
    quantita INT NOT NULL,
    prezzo_unitario INT NOT NULL,

    FOREIGN KEY (acquisto_id) REFERENCES acquisti(id),
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id)
);

INSERT INTO dettaglio_acquisti (acquisto_id, prodotto_id, quantita, prezzo_unitario) VALUES
(1, 1, 10, 20),
(1, 2, 20, 60),
(2, 3, 50, 20),
(2, 6, 100, 10);
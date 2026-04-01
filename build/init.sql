CREATE TABLE accounts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  owner_name VARCHAR(20) NOT NULL,
  currency VARCHAR(10) NOT NULL,
  created_at DATE NOT NULL,
  balance_after DOUBLE NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE transactions (
  id INT NOT NULL,
  account_id INT NOT NULL,
  type VARCHAR(10) NOT NULL,
  amount DOUBLE NOT NULL,
  description VARCHAR(100),
  created_at DATE NOT NULL,
  PRIMARY KEY(id, account_id),
  FOREIGN KEY(account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO accounts (owner_name, currency, created_at, balance_after)
VALUES
  ('Mario Rossi', 'EUR', '2024-02-10', 1500.00),
  ('Anna Bianchi', 'USD', '2024-02-12', 2300.00),
  ('Luca Verdi', 'EUR', '2024-03-01', 500.00),
  ('Chiara Neri', 'GBP', '2024-03-05', 1200.00),
  ('Giulia Moretti', 'EUR', '2024-03-10', 750.00);

INSERT INTO transactions (id, account_id, type, amount, description, created_at)
VALUES
  (1, 1, 'deposit', 1000.00, 'Versamento iniziale', '2024-02-10'),
  (2, 1, 'withdraw', 500.00, 'Pagamento bolletta luce', '2024-02-15'),
  (1, 2, 'deposit', 2300.00, 'Stipendio febbraio', '2024-02-12'),
  (1, 3, 'deposit', 500.00, 'Apertura conto', '2024-03-01'),
  (2, 4, 'deposit', 1200.00, 'Versamento iniziale', '2024-03-05'),
  (1, 5, 'deposit', 750.00, 'Apertura conto', '2024-03-10');

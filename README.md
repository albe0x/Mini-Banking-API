## Su Linux
`MY_UID=$(id -u) MY_GID=$(id -g) docker-compose up`

## Su Windows
`docker-compose up`

TODO:
  - TODO -> $app->get('/accounts/1/transactions',       "ConversionController:getTransaction");
  - TODO -> $app->get('/accounts/1/transactions/5',     "ConversionController:getTransactionNumber");

  
  - TODO -> $app->post('/accounts/1/deposits ',         "ConversionController:makeDepositit");
  - TODO -> $app->post('/accounts/1/withdrawals',       "ConversionController:makeWithdrawal");
  - TODO -> $app->put('/accounts/1/transactions/5 ',    "ConversionController:editTransactionNumber");
  - TODO -> $app->delete('/accounts/1/transactions/5',  "ConversionController:deleteTransactionNumber");
  
  per le 4 sopra creare matodi da usare in tutte le funzioni per controllare che il saldo non vada negativo,
  e calcolare il nuovo saldo. Controllare acnhe se e possibile modifiacre un operazione
  
  - TODO -> $app->get('/accounts/1/balance',            "ConversionController:getBalance");
  
  - TODO -> $app->get('/accounts/1/balance/convert/crypto?to=BTC', "TransactionsController:fiat");
  
  - CHECK -> $app->get('/accounts/1/balance/convert/crypto?to=BTC', "TransactionsController:crypto");


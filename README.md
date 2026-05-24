## Mini Banking API

## Creato da

- Alberto Bugli
- Federico Torna 
- Tommaso Fortuna

## API endpoints

- get /accounts/{id}/transactions
- get /accounts/{id}/transactions/{transaction_id}
- get /accounts/{id}/balance


- post /accounts/{id}/deposits
- post /accounts/{id}/withdrawals
```
  Json format per le post:
    `{
      "amount": 0.00,
      "description": "descrizione"
    }`
```

- put  /accounts/{id}/transactions/{transaction_id}
```modificare solo la descrizione```

- delete /accounts/{id}/transactions/{transaction_id}
```
  si può eliminare un movimento solo se il saldo finale rimane valido
```

- get /accounts/{id}/balance/convert/fiat?to={currency}
- get /accounts/{id}/balance/convert/crypto?to={currency}




## Su Linux
`MY_UID=$(id -u) MY_GID=$(id -g) docker-compose up`

# Infinite Pay for Magento

This magento extension integrates with InfinitePay api, allowing users to buy products from a magento store by credit card using InfinitePay.

## Author

* **Gabriel Mazurco (gabriel.mazurco@cloudwalk.io)**

## Features

## Inputs
```ts
  api_key: string,
  installments: {
    min: number,
    max: number
  }
```

sudo systemctl start mysql.service
sudo systemctl stop mysql.service

## Setup
```sh
# Start the development enviroment using the command bellow
# Note that you need to replace the path to your download magento folder in docker-compopse.yml
$ docker-compose up -d --build

# If you are using linux first run the commands and add this line to your hosts file: 127.0.0.1 local.magento
$ sudo vim /etc/hosts

# If you need to access development machine run
$ docker exec -it magento_web_1 /bin/bash

# If you wish to stop docker containers
$ docker-compose down
```

## Development access
user: user
password: bitnami1
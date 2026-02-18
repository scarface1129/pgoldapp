# PGoldApp

A crypto trading API I built for buying/selling BTC, ETH, and USDT with Nigerian Naira. Nothing fancy, just a clean REST API that gets the job done.

## What it does

- User signup/login (Sanctum tokens)
- Naira wallet - deposit, withdraw, check balance
- Buy and sell crypto at live rates (pulled from CoinGecko)
- 1.5% fee on trades
- Transaction history and portfolio tracking

## Tech

- Laravel 12 / PHP 8.2
- MySQL
- PHPUnit for tests

## Getting Started

You'll need PHP 8.2+, Composer, and MySQL.

```bash
# clone and install
git clone <repo-url>
cd pgoldapp
composer install

# setup env
cp .env.example .env
php artisan key:generate

# update .env with your DB creds
# DB_DATABASE=pgoldapp
# DB_USERNAME=root
# DB_PASSWORD=

# run migrations
php artisan migrate
php artisan db:seed

# start server
php artisan serve
```

Should be running at `http://localhost:8000`

### Test accounts

The seeder creates two accounts you can play with:

- `test@example.com` / `password123` (has ₦500k balance)
- `test2@example.com` / `password123` (empty wallet)

## API Endpoints

Check [docs/API.md](docs/API.md) for the full docs, but here's the gist:

**Auth:**
- `POST /api/v1/auth/register` - signup
- `POST /api/v1/auth/login` - login
- `POST /api/v1/auth/logout` - logout (needs token)
- `GET /api/v1/auth/profile` - get your profile

**Wallet:**
- `GET /api/v1/wallet` - check balance
- `POST /api/v1/wallet/deposit` - add funds
- `POST /api/v1/wallet/withdraw` - withdraw
- `GET /api/v1/wallet/transactions` - tx history

**Trading:**
- `GET /api/v1/crypto/prices` - current prices
- `GET /api/v1/crypto/supported` - supported coins
- `POST /api/v1/trade/buy` - buy crypto
- `POST /api/v1/trade/sell` - sell crypto
- `POST /api/v1/trade/quote` - get a quote first
- `GET /api/v1/trade/history` - your trades
- `GET /api/v1/crypto/portfolio` - holdings + values

### Quick example

```bash
# login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

# copy the token from response, then buy some BTC
curl -X POST http://localhost:8000/api/v1/trade/buy \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"symbol":"BTC","amount":50000}'
```

## How fees work

Pretty simple - 1.5% on buys and sells.

**Buying:** You say "I want to spend ₦100k on BTC", we take ₦1,500 fee, you get ₦98,500 worth of BTC.

**Selling:** You sell 0.001 BTC worth ₦67,500, we take ₦1,012.50, you get ₦66,487.50.

Fees are in the database so they can be changed without touching code.

## CoinGecko

Prices come from CoinGecko's free API. I cache them for 60 seconds so we don't hammer their servers (and stay within rate limits). If their API goes down, the error handling is decent but not bulletproof.

## Running tests

```bash
php artisan test

# or specific file
php artisan test tests/Feature/TradingTest.php
```

Tests mock CoinGecko so they don't make real API calls.

## Architecture notes

I went with a service layer pattern - keeps the controllers skinny and makes testing easier. The main services are:

- `WalletService` - handles deposits, withdrawals, balance stuff
- `TradingService` - executes trades, calculates fees
- `CoinGeckoService` - fetches and caches prices

All trades run in database transactions so if something fails mid-trade, everything rolls back.

## What I'd add with more time

Honestly there's a lot this is missing for production:

- Queue-based trading (right now it's synchronous which can be slow)
- Proper rate limiting
- 2FA
- KYC/verification
- More coins
- Price alerts
- Better error handling
- Admin dashboard
